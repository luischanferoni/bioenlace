<?php

namespace common\components\Domain\Organization\Service\Billing;

use common\models\BillingAccount;
use common\models\BillingAccountEfector;
use common\models\BillingSignupRequest;
use common\models\Clinical\EncounterDefinition;
use common\models\Efector;
use common\models\Localidad;
use common\models\Person\Persona;
use common\models\Servicio;
use common\models\ServiciosEfector;
use common\models\User;
use common\components\Domain\Organization\Service\ProfesionalEfectorServicio\ProfesionalEfectorServicioAltaService;
use common\components\Platform\Core\Product\PricingPesByEncounterClassMetadata;
use Symfony\Component\Yaml\Yaml;
use Yii;

/**
 * Alta self-service de clínica/efector + AdminEfector + licencia (pago simulado).
 */
final class InstitutionalEfectorSignupService
{
    public const SECTOR_PUBLICO = 'PUBLICO';

    public const SECTOR_PRIVADO = 'PRIVADO';

    /** Clínica / centro (N profesionales). */
    public const PERFIL_CLINICA = 'CLINICA';

    /** Profesional independiente = efector unipersonal (mismo modelo, default max_pes=1). */
    public const PERFIL_CONSULTORIO = 'CONSULTORIO';

    public const ITEM_NAME_ADMIN_EFECTOR = 'AdminEfector';

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public static function register(array $payload): array
    {
        $perfil = strtoupper(trim((string) ($payload['perfil'] ?? self::PERFIL_CLINICA)));
        if (!in_array($perfil, [self::PERFIL_CLINICA, self::PERFIL_CONSULTORIO], true)) {
            throw new \InvalidArgumentException('Perfil de alta inválido (CLINICA o CONSULTORIO).');
        }

        $sector = strtoupper(trim((string) ($payload['sector'] ?? '')));
        if (!in_array($sector, [self::SECTOR_PUBLICO, self::SECTOR_PRIVADO], true)) {
            throw new \InvalidArgumentException('Indicá si el efector es PUBLICO o PRIVADO.');
        }

        $idMinisterio = (int) ($payload['id_billing_account_ministerio'] ?? 0);
        if ($sector === self::SECTOR_PUBLICO && $idMinisterio <= 0) {
            throw new \InvalidArgumentException('Para un efector público debés elegir el ministerio.');
        }

        $ministerio = null;
        if ($idMinisterio > 0) {
            $ministerio = BillingAccount::findOne([
                'id' => $idMinisterio,
                'tipo' => BillingAccount::TIPO_MINISTERIO,
                'activo' => 1,
                'deleted_at' => null,
            ]);
            if ($ministerio === null) {
                throw new \InvalidArgumentException('Ministerio inválido o inactivo.');
            }
        }

        $pagoPorMinisterio = !empty($payload['pago_cubierto_por_ministerio']);
        if ($perfil === self::PERFIL_CONSULTORIO && $sector === self::SECTOR_PUBLICO) {
            throw new \InvalidArgumentException(
                'El alta de consultorio profesional solo admite sector privado. '
                . 'Si trabajás en un centro público, pedí que administración del centro te sume a Bioenlace.'
            );
        }
        if ($pagoPorMinisterio && ($sector !== self::SECTOR_PUBLICO || $ministerio === null)) {
            throw new \InvalidArgumentException('La cobertura ministerial solo aplica a efectores públicos con ministerio.');
        }

        $admin = self::normalizeAdmin($payload['admin'] ?? []);
        $efectorData = self::normalizeEfector($payload['efector'] ?? [], $sector, $perfil);
        $plan = self::normalizePlan($payload['plan'] ?? [], $perfil);
        $paymentIn = is_array($payload['payment'] ?? null) ? $payload['payment'] : [];

        $tx = Yii::$app->db->beginTransaction();
        try {
            [$persona, $user] = self::createPersonaAndUser($admin);

            $efector = self::createEfector($efectorData);
            $idEfector = (int) $efector->id_efector;

            $account = BillingAccountService::createAccount([
                'nombre' => 'Licencia — ' . $efector->nombre,
                'tipo' => BillingAccount::TIPO_EFECTOR,
                'notas' => $perfil === self::PERFIL_CONSULTORIO
                    ? 'Alta self-service consultorio unipersonal'
                    : 'Alta self-service institucional',
                'activo' => $pagoPorMinisterio ? 0 : 1,
            ]);
            $account->owner_user_id = (int) $user->id;
            $account->save(false, ['owner_user_id', 'updated_at']);

            $amount = self::estimateMonthlyUsd($plan);
            $payment = null;
            if (!$pagoPorMinisterio) {
                $payment = SimulatedPaymentGateway::charge([
                    'id_billing_account' => (int) $account->id,
                    'amount_usd' => $amount,
                    'card_number' => $paymentIn['card_number'] ?? null,
                    'card_holder' => $paymentIn['card_holder'] ?? ($admin['nombre'] . ' ' . $admin['apellido']),
                    'currency' => 'USD',
                ]);
                foreach ($plan['classes'] as $class => $cfg) {
                    BillingAccountService::upsertEntitlement((int) $account->id, $class, [
                        'max_pes' => (int) $cfg['max_pes'],
                        'dictado_incluido' => !empty($cfg['dictado_incluido']),
                        'videollamada_permitida' => !empty($cfg['videollamada_permitida']),
                        'activo' => 1,
                    ]);
                }
                BillingAccountService::attachEfector((int) $account->id, $idEfector, BillingAccountEfector::ROL_POOL);
            }

            if ($ministerio !== null) {
                BillingAccountService::attachEfector(
                    (int) $ministerio->id,
                    $idEfector,
                    BillingAccountEfector::ROL_AFILIADO
                );
            }

            self::ensureAdminEfectorPes((int) $persona->id_persona, $idEfector, (int) $user->id);

            $log = new BillingSignupRequest();
            $log->tipo = BillingSignupRequest::TIPO_EFECTOR;
            $log->status = $pagoPorMinisterio
                ? BillingSignupRequest::STATUS_PENDING
                : BillingSignupRequest::STATUS_APPROVED;
            $log->nombre_organizacion = (string) $efector->nombre;
            $log->sector = $sector;
            $log->id_billing_account_ministerio = $ministerio !== null ? (int) $ministerio->id : null;
            $log->contacto_nombre = $admin['nombre'];
            $log->contacto_apellido = $admin['apellido'];
            $log->contacto_email = $admin['email'];
            $log->contacto_telefono = $admin['telefono'];
            $log->contacto_documento = $admin['documento'];
            $log->notas = ($perfil === self::PERFIL_CONSULTORIO ? '[CONSULTORIO] ' : '')
                . ($pagoPorMinisterio
                    ? 'Solicita cobertura de pago del ministerio; pendiente de aprobación operativa.'
                    : 'Alta self-service con pago simulado.');
            $log->id_user = (int) $user->id;
            $log->id_billing_account = (int) $account->id;
            $log->id_efector = $idEfector;
            if (!$pagoPorMinisterio) {
                $log->reviewed_at = date('Y-m-d H:i:s');
            }
            $log->save(false);

            $tx->commit();

            return [
                'id_user' => (int) $user->id,
                'id_persona' => (int) $persona->id_persona,
                'id_efector' => $idEfector,
                'id_billing_account' => (int) $account->id,
                'perfil' => $perfil,
                'sector' => $sector,
                'pago_cubierto_por_ministerio' => $pagoPorMinisterio,
                'amount_usd_charged' => $payment !== null ? (float) $payment->amount_usd : 0.0,
                'payment_reference' => $payment !== null ? (string) $payment->external_reference : null,
                'username' => (string) $user->username,
                'email' => (string) $user->email,
                'login_hint' => 'Ingresá en la web clínica con tu e-mail/usuario y la contraseña elegida.',
                'next_steps' => self::nextStepsForPerfil($perfil),
            ];
        } catch (\Throwable $e) {
            $tx->rollBack();
            throw $e;
        }
    }

    /**
     * @return list<string>
     */
    public static function nextStepsForPerfil(string $perfil): array
    {
        if ($perfil === self::PERFIL_CONSULTORIO) {
            return [
                'Ingresá a la web clínica con tu usuario y contraseña.',
                'En el asistente o en la gestión del centro, asignate a vos mismo en un servicio clínico (por ejemplo medicina general / ambulatorio) para poder atender.',
                'La administración del consultorio ya está habilitada; el paso clínico es el que te permite agenda y captura.',
            ];
        }

        return [
            'Ingresá a la web clínica con tu usuario y contraseña.',
            'Desde administración del centro podés invitar profesionales y habilitar servicios.',
        ];
    }

    /**
     * @return list<array{id: int, nombre: string}>
     */
    public static function listMinisteriosActivos(): array
    {
        $rows = BillingAccount::find()
            ->where([
                'tipo' => BillingAccount::TIPO_MINISTERIO,
                'activo' => 1,
                'deleted_at' => null,
            ])
            ->orderBy(['nombre' => SORT_ASC])
            ->all();

        $out = [];
        foreach ($rows as $row) {
            $out[] = [
                'id' => (int) $row->id,
                'nombre' => (string) $row->nombre,
            ];
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    public static function planesCatalog(): array
    {
        $path = Yii::getAlias('@common/metadata/bioenlace/organization/pricing-pes-by-encounter-class.yaml');
        if (!is_file($path)) {
            return ['version' => 0, 'sellable_classes' => []];
        }
        $data = Yaml::parseFile($path);

        return is_array($data) ? $data : ['version' => 0, 'sellable_classes' => []];
    }

    /**
     * @param array<string, mixed> $admin
     * @return array{nombre: string, apellido: string, email: string, password: string, documento: string, telefono: ?string, fecha_nacimiento: string}
     */
    private static function normalizeAdmin(array $admin): array
    {
        $nombre = trim((string) ($admin['nombre'] ?? ''));
        $apellido = trim((string) ($admin['apellido'] ?? ''));
        $email = strtolower(trim((string) ($admin['email'] ?? '')));
        $password = (string) ($admin['password'] ?? '');
        $documento = preg_replace('/\D+/', '', (string) ($admin['documento'] ?? '')) ?? '';
        $telefono = trim((string) ($admin['telefono'] ?? ''));
        $fn = trim((string) ($admin['fecha_nacimiento'] ?? '1980-01-01'));

        if ($nombre === '' || $apellido === '' || $email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Datos del administrador incompletos (nombre, apellido, e-mail).');
        }
        if (strlen($password) < 8) {
            throw new \InvalidArgumentException('La contraseña debe tener al menos 8 caracteres.');
        }
        if ($documento === '' || strlen($documento) < 7) {
            throw new \InvalidArgumentException('Indicá un documento válido del administrador.');
        }
        if (User::find()->where(['or', ['email' => $email], ['username' => $email]])->exists()) {
            throw new \InvalidArgumentException('Ya existe un usuario con ese e-mail.');
        }

        return [
            'nombre' => $nombre,
            'apellido' => $apellido,
            'email' => $email,
            'password' => $password,
            'documento' => $documento,
            'telefono' => $telefono !== '' ? $telefono : null,
            'fecha_nacimiento' => $fn,
        ];
    }

    /**
     * @param array<string, mixed> $efector
     * @return array{nombre: string, domicilio: string, telefono: ?string, id_localidad: int, origen_financiamiento: string, dependencia: string, tipologia: string, codigo_sisa: string}
     */
    private static function normalizeEfector(array $efector, string $sector, string $perfil = self::PERFIL_CLINICA): array
    {
        $nombre = trim((string) ($efector['nombre'] ?? ''));
        $domicilio = trim((string) ($efector['domicilio'] ?? ''));
        if ($nombre === '' || $domicilio === '') {
            throw new \InvalidArgumentException('Indicá nombre y domicilio del efector.');
        }

        $idLocalidad = (int) ($efector['id_localidad'] ?? 0);
        if ($idLocalidad <= 0) {
            $idLocalidad = self::resolveDefaultLocalidadId();
        }
        if (Localidad::findOne(['id_localidad' => $idLocalidad]) === null) {
            throw new \InvalidArgumentException('Localidad inválida.');
        }

        $publico = $sector === self::SECTOR_PUBLICO;
        $tipologia = $perfil === self::PERFIL_CONSULTORIO
            ? 'CLIN'
            : ($publico ? 'CAP' : 'CLIN');

        return [
            'nombre' => $nombre,
            'domicilio' => $domicilio,
            'telefono' => ($t = trim((string) ($efector['telefono'] ?? ''))) !== '' ? $t : null,
            'id_localidad' => $idLocalidad,
            'origen_financiamiento' => $publico ? 'Público' : 'Privado',
            'dependencia' => $publico ? 'Provincial' : 'Privado',
            'tipologia' => $tipologia,
            'codigo_sisa' => self::generateCodigoSisa(),
        ];
    }

    /**
     * @param array<string, mixed> $plan
     * @return array{classes: array<string, array{max_pes: int, dictado_incluido: bool, videollamada_permitida: bool}>}
     */
    private static function normalizePlan(array $plan, string $perfil = self::PERFIL_CLINICA): array
    {
        $raw = $plan['classes'] ?? null;
        if (!is_array($raw) || $raw === []) {
            // Legacy / vacío: consultorio → AMB×1; clínica sin classes → error (no forzar AMB).
            if ($perfil === self::PERFIL_CONSULTORIO) {
                $raw = [
                    'AMB' => [
                        'max_pes' => 1,
                        'dictado_incluido' => !empty($plan['audio']),
                        'videollamada_permitida' => !empty($plan['videollamada']),
                    ],
                ];
            } else {
                throw new \InvalidArgumentException('Elegí al menos un tipo de atención (AMB / EMER / IMP).');
            }
        }

        $classes = [];
        foreach ($raw as $class => $cfg) {
            $class = strtoupper(trim((string) $class));
            if (!isset(EncounterDefinition::ENCOUNTER_CLASS[$class])) {
                throw new \InvalidArgumentException('Clase de atención inválida: ' . $class);
            }
            if (!is_array($cfg)) {
                continue;
            }
            $attentions = max(0, (int) ($cfg['attentions_per_month'] ?? 0));
            $max = (int) ($cfg['max_pes'] ?? 0);
            if ($attentions <= 0 && $max > 0) {
                $attentions = $max * (int) PricingPesByEncounterClassMetadata::referenceEncountersPerMonth();
            }
            if ($attentions <= 0) {
                continue;
            }
            if ($max <= 0) {
                $max = PricingPesByEncounterClassMetadata::deriveMaxPesFromAttentions($attentions);
            }
            $max = max(1, $max);
            $dictado = !empty($cfg['dictado_incluido']) || in_array($class, ['EMER', 'IMP'], true);
            $video = $class === 'AMB' && !empty($cfg['videollamada_permitida']);
            $classes[$class] = [
                'max_pes' => $max,
                'attentions_per_month' => $attentions,
                'dictado_incluido' => $dictado,
                'videollamada_permitida' => $video,
            ];
        }
        if ($classes === []) {
            throw new \InvalidArgumentException('Elegí al menos un tipo de atención (AMB / EMER / IMP).');
        }

        if ($perfil === self::PERFIL_CONSULTORIO) {
            if (!isset($classes['AMB'])) {
                throw new \InvalidArgumentException('El consultorio profesional requiere ambulatorio.');
            }
            if (isset($classes['EMER']) || isset($classes['IMP'])) {
                throw new \InvalidArgumentException(
                    'El consultorio profesional solo admite ambulatorio. Para urgencia o internación usá el alta de clínica / centro.'
                );
            }
            // Unipersonal: cupo fijo en 1 (ignora max_pes del payload).
            $classes['AMB']['max_pes'] = 1;
            if (($classes['AMB']['attentions_per_month'] ?? 0) <= 0) {
                $classes['AMB']['attentions_per_month'] = (int) PricingPesByEncounterClassMetadata::referenceEncountersPerMonth();
            }
        }

        return ['classes' => $classes];
    }

    /**
     * @param array{classes: array<string, array{max_pes?: int, attentions_per_month?: int, dictado_incluido: bool, videollamada_permitida: bool}>}|array<string, array{max_pes?: int, attentions_per_month?: int, dictado_incluido: bool, videollamada_permitida: bool}> $plan
     */
    public static function estimateMonthlyUsd(array $plan): float
    {
        if (!isset($plan['classes']) || !is_array($plan['classes'])) {
            $plan = ['classes' => $plan];
        }

        $byClass = [];
        $audio = false;
        $video = false;
        $ref = (int) PricingPesByEncounterClassMetadata::referenceEncountersPerMonth();
        foreach ($plan['classes'] as $class => $cfg) {
            if (!is_array($cfg)) {
                continue;
            }
            $attentions = (int) ($cfg['attentions_per_month'] ?? 0);
            if ($attentions <= 0) {
                $maxPes = (int) ($cfg['max_pes'] ?? 0);
                $attentions = $maxPes > 0 ? $maxPes * $ref : 0;
            }
            if ($attentions <= 0) {
                continue;
            }
            $code = (string) $class;
            $byClass[$code] = $attentions;
            if ($code === 'AMB') {
                $audio = $audio || !empty($cfg['dictado_incluido']);
                $video = $video || !empty($cfg['videollamada_permitida']);
            }
        }

        return PricingPesByEncounterClassMetadata::estimateMonthlyTotal($byClass, $audio, $video);
    }

    /**
     * @param array{nombre: string, apellido: string, email: string, password: string, documento: string, telefono: ?string, fecha_nacimiento: string} $admin
     * @return array{0: Persona, 1: User}
     */
    private static function createPersonaAndUser(array $admin): array
    {
        $persona = new Persona();
        $persona->scenario = Persona::SCENARIOCREATEUPDATE;
        $persona->nombre = $admin['nombre'];
        $persona->apellido = $admin['apellido'];
        $persona->documento = $admin['documento'];
        $persona->id_tipodoc = 1;
        $persona->fecha_nacimiento = $admin['fecha_nacimiento'];
        $persona->sexo_biologico = 1;
        $persona->genero = 1;
        $persona->id_estado_civil = 1;
        $persona->acredita_identidad = 1;
        if (!$persona->save()) {
            throw new \InvalidArgumentException('No se pudo crear la persona: ' . json_encode($persona->getErrors()));
        }

        $user = new User();
        $user->username = $admin['email'];
        $user->email = $admin['email'];
        $user->status = User::STATUS_ACTIVE;
        $user->setPassword($admin['password']);
        $user->password_set_at = time();
        $user->generateAuthKey();
        $user->email_confirmed = 1;
        if (!$user->save()) {
            throw new \InvalidArgumentException('No se pudo crear el usuario: ' . json_encode($user->getErrors()));
        }

        $persona->id_user = (int) $user->id;
        $persona->scenario = Persona::SCENARIOUSERUPDATE;
        if (!$persona->save(false)) {
            throw new \RuntimeException('No se pudo vincular usuario a persona.');
        }

        return [$persona, $user];
    }

    /**
     * @param array{nombre: string, domicilio: string, telefono: ?string, id_localidad: int, origen_financiamiento: string, dependencia: string, tipologia: string, codigo_sisa: string} $data
     */
    private static function createEfector(array $data): Efector
    {
        $efector = new Efector();
        $efector->codigo_sisa = $data['codigo_sisa'];
        $efector->nombre = $data['nombre'];
        $efector->dependencia = $data['dependencia'];
        $efector->tipologia = $data['tipologia'];
        $efector->domicilio = $data['domicilio'];
        $efector->origen_financiamiento = $data['origen_financiamiento'];
        $efector->id_localidad = $data['id_localidad'];
        $efector->telefono = $data['telefono'];
        $efector->estado = 'ACTIVO';
        $efector->grupo = '0';
        $efector->implementado = 'F';
        if (!$efector->save()) {
            throw new \InvalidArgumentException('No se pudo crear el efector: ' . json_encode($efector->getErrors()));
        }

        return $efector;
    }

    private static function ensureAdminEfectorPes(int $idPersona, int $idEfector, int $actingUserId): void
    {
        $servicio = Servicio::find()->where(['item_name' => self::ITEM_NAME_ADMIN_EFECTOR])->one();
        if ($servicio === null) {
            throw new \RuntimeException('Servicio AdminEfector no configurado en el sistema.');
        }
        $idServicio = (int) $servicio->id_servicio;

        $exists = ServiciosEfector::findActive()
            ->where(['id_servicio' => $idServicio, 'id_efector' => $idEfector])
            ->exists();
        if (!$exists) {
            $now = date('Y-m-d H:i:s');
            Yii::$app->db->createCommand()->insert('{{%servicios_efector}}', [
                'id_servicio' => $idServicio,
                'id_efector' => $idEfector,
                'formas_atencion' => ServiciosEfector::DELEGAR_A_CADA_PROFESIONAL,
                'pase_previo' => 0,
                'created_by' => $actingUserId,
                'updated_by' => $actingUserId,
                'created_at' => $now,
                'updated_at' => $now,
            ])->execute();
        }

        ProfesionalEfectorServicioAltaService::ensurePersonaServicioEnEfector(
            $idPersona,
            $idEfector,
            $idServicio,
            $actingUserId
        );
    }

    private static function generateCodigoSisa(): string
    {
        // max 15 (regla Efector)
        do {
            $code = 'W' . strtoupper(substr(bin2hex(random_bytes(7)), 0, 14));
        } while (Efector::find()->where(['codigo_sisa' => $code])->exists());

        return substr($code, 0, 15);
    }

    private static function resolveDefaultLocalidadId(): int
    {
        $configured = (int) (Yii::$app->params['institutional_signup_default_id_localidad'] ?? 0);
        if ($configured > 0 && Localidad::findOne(['id_localidad' => $configured]) !== null) {
            return $configured;
        }
        $id = (int) Localidad::find()->select('id_localidad')->orderBy(['id_localidad' => SORT_ASC])->scalar();
        if ($id <= 0) {
            throw new \InvalidArgumentException('No hay localidades cargadas; indicá id_localidad.');
        }

        return $id;
    }
}
