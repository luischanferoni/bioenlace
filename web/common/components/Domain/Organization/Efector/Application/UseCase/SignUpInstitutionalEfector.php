<?php

namespace common\components\Domain\Organization\Efector\Application\UseCase;

use common\models\Organization\BillingAccount;
use common\models\Organization\BillingAccountEfector;
use common\models\Organization\BillingPayment;
use common\models\Organization\BillingSignupRequest;
use common\models\Clinical\EncounterDefinition;
use common\models\Organization\Efector;
use common\models\Geo\Localidad;
use common\models\Person\Persona;
use common\models\Platform\User;
use common\components\Domain\Organization\Pes\Application\UseCase\EnsureAdminEfectorAssignment;
use common\components\Domain\Organization\Efector\Application\Service\ContactPhoneValidator;
use common\components\Platform\Core\Product\PricingPesByEncounterClassMetadata;
use Yii;

/**
 * Alta self-service de clínica/efector + AdminEfector + licencia.
 */
final class SignUpInstitutionalEfector
{
    public const SECTOR_PUBLICO = 'PUBLICO';

    public const SECTOR_PRIVADO = 'PRIVADO';

    /** Clínica / centro (N profesionales). */
    public const PERFIL_CLINICA = 'CLINICA';

    /** Profesional independiente = efector unipersonal (mismo modelo, default max_pes=1). */
    public const PERFIL_CONSULTORIO = 'CONSULTORIO';

    /** @deprecated usar {@see EnsureAdminEfectorAssignment::ITEM_NAME} */
    public const ITEM_NAME_ADMIN_EFECTOR = EnsureAdminEfectorAssignment::ITEM_NAME;

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public static function register(
        array $payload,
        User $verifiedUser,
        Persona $verifiedPersona,
        array $confirmedPayment
    ): array
    {
        $draft = self::prepareDraft($payload);
        $perfil = $draft['perfil'];
        $sector = $draft['sector'];
        $ministerio = $draft['ministerio'];
        $pagoPorMinisterio = !empty($payload['pago_cubierto_por_ministerio']);
        if ($pagoPorMinisterio) {
            throw new \InvalidArgumentException('No se puede combinar el checkout propio con una cobertura ministerial.');
        }

        $identity = self::resolveVerifiedIdentity($payload, $verifiedUser, $verifiedPersona);
        $admin = $identity['admin'];
        $efectorData = $draft['efector'];
        $plan = $draft['plan'];

        $tx = Yii::$app->db->beginTransaction();
        try {
            [$persona, $user] = [$identity['persona'], $identity['user']];

            if ((int) ($efectorData['id_efector'] ?? 0) > 0) {
                $efector = Efector::find()
                    ->where([
                        'id_efector' => (int) $efectorData['id_efector'],
                        'codigo_sisa' => (string) $efectorData['codigo_sisa'],
                    ])
                    ->forUpdate()
                    ->one();
                if ($efector === null) {
                    throw new \InvalidArgumentException('El efector SISA dejó de estar disponible.');
                }
                self::assertPublicEfectorAvailable($efector);
            } else {
                $efector = self::createEfector($efectorData);
            }
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
            if (!$account->save(false, ['owner_user_id', 'updated_at'])) {
                throw new \RuntimeException('No se pudo asignar el titular de la cuenta comercial.');
            }

            $amount = self::estimateMonthlyUsd($plan);
            $payment = self::recordConfirmedPayment(
                (int) $account->id,
                (float) ($confirmedPayment['amount_usd'] ?? $amount),
                $confirmedPayment
            );
            foreach ($plan['classes'] as $class => $cfg) {
                BillingAccountService::upsertEntitlement((int) $account->id, $class, [
                    'max_pes' => (int) $cfg['max_pes'],
                    'dictado_incluido' => !empty($cfg['dictado_incluido']),
                    'videollamada_permitida' => !empty($cfg['videollamada_permitida']),
                    'activo' => 1,
                ]);
            }
            BillingAccountService::attachEfector((int) $account->id, $idEfector, BillingAccountEfector::ROL_POOL);

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
            $log->status = BillingSignupRequest::STATUS_APPROVED;
            $log->nombre_organizacion = (string) $efector->nombre;
            $log->sector = $sector;
            $log->id_billing_account_ministerio = $ministerio !== null ? (int) $ministerio->id : null;
            $log->contacto_nombre = $admin['nombre'];
            $log->contacto_apellido = $admin['apellido'];
            $log->contacto_email = $admin['email'];
            $log->contacto_telefono = $admin['telefono'];
            $log->contacto_documento = $admin['documento'];
            $log->notas = ($perfil === self::PERFIL_CONSULTORIO ? '[CONSULTORIO] ' : '')
                . 'Alta self-service con primer pago confirmado por Mercado Pago.';
            $log->id_user = (int) $user->id;
            $log->id_billing_account = (int) $account->id;
            $log->id_efector = $idEfector;
            $log->reviewed_at = date('Y-m-d H:i:s');
            if (!$log->save(false)) {
                throw new \RuntimeException('No se pudo registrar la solicitud de alta comercial.');
            }

            $tx->commit();

            return [
                'id_user' => (int) $user->id,
                'id_persona' => (int) $persona->id_persona,
                'id_efector' => $idEfector,
                'id_billing_account' => (int) $account->id,
                'perfil' => $perfil,
                'sector' => $sector,
                'pago_cubierto_por_ministerio' => false,
                'amount_usd_charged' => (float) $payment->amount_usd,
                'payment_reference' => (string) $payment->external_reference,
                'amount_ars_charged' => (float) $payment->amount_ars,
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
     * Valida y normaliza los datos comerciales sin crear entidades ni cobrar.
     *
     * @param array<string, mixed> $payload
     * @return array{perfil: string, sector: string, ministerio: ?BillingAccount, efector: array<string, mixed>, plan: array<string, mixed>, amount_usd: float}
     */
    public static function prepareDraft(array $payload): array
    {
        $perfil = strtoupper(trim((string) ($payload['perfil'] ?? self::PERFIL_CLINICA)));
        if (!in_array($perfil, [self::PERFIL_CLINICA, self::PERFIL_CONSULTORIO], true)) {
            throw new \InvalidArgumentException('Perfil de alta inválido (CLINICA o CONSULTORIO).');
        }

        $sector = strtoupper(trim((string) ($payload['sector'] ?? '')));
        if (!in_array($sector, [self::SECTOR_PUBLICO, self::SECTOR_PRIVADO], true)) {
            throw new \InvalidArgumentException('Indicá si el efector es PUBLICO o PRIVADO.');
        }
        if (!is_array($payload['efector'] ?? null)) {
            throw new \InvalidArgumentException('Los datos del efector son inválidos.');
        }
        if (!is_array($payload['plan'] ?? null)) {
            throw new \InvalidArgumentException('Los datos del plan son inválidos.');
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

        if ($perfil === self::PERFIL_CONSULTORIO && $sector === self::SECTOR_PUBLICO) {
            throw new \InvalidArgumentException(
                'El alta de consultorio profesional solo admite sector privado. '
                . 'Si trabajás en un centro público, pedí que administración del centro te sume a Bioenlace.'
            );
        }

        if ($sector === self::SECTOR_PUBLICO) {
            $efector = self::resolvePublicEfector($payload['efector']);
            self::assertAllowedCountry((int) $efector['id_localidad']);
        } else {
            if ((int) ($payload['efector']['id_localidad'] ?? 0) <= 0) {
                throw new \InvalidArgumentException('Seleccioná una localidad válida para el efector.');
            }
            $efector = self::normalizeEfector($payload['efector'], $sector, $perfil);
        }
        $plan = self::normalizePlan($payload['plan'] ?? [], $perfil);
        $amountUsd = self::estimateMonthlyUsd($plan);
        if ($amountUsd <= 0 || !is_finite($amountUsd)) {
            throw new \InvalidArgumentException('No se pudo calcular un importe válido para el plan.');
        }

        return [
            'perfil' => $perfil,
            'sector' => $sector,
            'ministerio' => $ministerio,
            'efector' => $efector,
            'plan' => $plan,
            'amount_usd' => $amountUsd,
        ];
    }

    /**
     * Resuelve el efector desde el catálogo local alimentado por el archivo oficial SISA.
     *
     * @return array{id_efector: int, codigo_sisa: string, nombre: string, domicilio: string, telefono: ?string, id_localidad: int, origen_financiamiento: string, dependencia: string, tipologia: string}
     */
    private static function resolvePublicEfector(array $input): array
    {
        $codigoSisa = strtoupper(trim((string) ($input['codigo_sisa'] ?? '')));
        if (!preg_match('/^[A-Z0-9]{1,15}$/', $codigoSisa)
            || str_starts_with($codigoSisa, 'W')
            || str_starts_with($codigoSisa, 'DEV')) {
            throw new \InvalidArgumentException('Ingresá un código SISA oficial válido.');
        }

        $efector = Efector::findOne(['codigo_sisa' => $codigoSisa]);
        if ($efector === null || strtoupper(trim((string) $efector->estado)) === 'INACTIVO') {
            throw new \InvalidArgumentException(
                'El código SISA no está cargado como activo en el catálogo local. Revisá el código o solicitá actualizar el catálogo.'
            );
        }
        if (stripos((string) $efector->origen_financiamiento, 'privado') !== false) {
            throw new \InvalidArgumentException('El código SISA corresponde a un efector privado.');
        }

        self::assertPublicEfectorAvailable($efector);

        return [
            'id_efector' => (int) $efector->id_efector,
            'codigo_sisa' => (string) $efector->codigo_sisa,
            'nombre' => (string) $efector->nombre,
            'domicilio' => (string) $efector->domicilio,
            'telefono' => trim((string) $efector->telefono) !== '' ? (string) $efector->telefono : null,
            'id_localidad' => (int) $efector->id_localidad,
            'origen_financiamiento' => (string) $efector->origen_financiamiento,
            'dependencia' => (string) $efector->dependencia,
            'tipologia' => (string) $efector->tipologia,
        ];
    }

    private static function assertPublicEfectorAvailable(Efector $efector): void
    {
        $hasMinistry = BillingAccountEfector::find()
            ->alias('membership')
            ->innerJoin(
                ['account' => BillingAccount::tableName()],
                'account.id = membership.id_billing_account'
            )
            ->where([
                'membership.id_efector' => (int) $efector->id_efector,
                'membership.rol_membresia' => BillingAccountEfector::ROL_AFILIADO,
                'membership.deleted_at' => null,
                'account.tipo' => BillingAccount::TIPO_MINISTERIO,
                'account.deleted_at' => null,
            ])
            ->exists();
        if ($hasMinistry) {
            throw new \InvalidArgumentException(
                'Este efector ya está afiliado a un ministerio y no puede registrarse otra vez.'
            );
        }

        $hasBillingPool = BillingAccountEfector::find()
            ->where([
                'id_efector' => (int) $efector->id_efector,
                'rol_membresia' => BillingAccountEfector::ROL_POOL,
                'deleted_at' => null,
            ])
            ->exists();
        if ($hasBillingPool) {
            throw new \InvalidArgumentException(
                'Este efector ya tiene una cuenta de facturación. Contactá al administrador para continuar.'
            );
        }
    }

    /**
     * @return array{admin: array<string, mixed>, persona: Persona, user: User}
     */
    private static function resolveVerifiedIdentity(array $payload, User $user, Persona $persona): array
    {
        if ((int) $persona->id_user !== (int) $user->id || (int) $user->status !== User::STATUS_ACTIVE) {
            throw new \InvalidArgumentException('La cuenta autenticada no está vinculada a una persona activa.');
        }

        $email = strtolower(trim((string) ($user->email ?: ($payload['payer_email'] ?? ''))));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('La cuenta debe tener un e-mail válido para el alta.');
        }
        $telefono = trim((string) ($payload['contact_phone'] ?? ''));
        ContactPhoneValidator::assertValid($telefono, 'Teléfono del administrador');

        return [
            'admin' => [
                'nombre' => (string) $persona->nombre,
                'apellido' => (string) $persona->apellido,
                'email' => $email,
                'password' => '',
                'documento' => (string) $persona->documento,
                'telefono' => $telefono !== '' ? $telefono : null,
                'fecha_nacimiento' => (string) $persona->fecha_nacimiento,
            ],
            'persona' => $persona,
            'user' => $user,
        ];
    }

    /**
     * @param array<string, mixed> $confirmedPayment
     */
    private static function recordConfirmedPayment(
        int $idBillingAccount,
        float $amountUsd,
        array $confirmedPayment
    ): BillingPayment {
        $providerPaymentId = trim((string) ($confirmedPayment['provider_payment_id'] ?? ''));
        $providerSubscriptionId = trim((string) ($confirmedPayment['provider_subscription_id'] ?? ''));
        $amountUsd = (float) ($confirmedPayment['amount_usd'] ?? $amountUsd);
        $amountArs = (float) ($confirmedPayment['amount_ars'] ?? 0);
        if ($providerPaymentId === '' || $providerSubscriptionId === ''
            || $amountArs <= 0 || $amountUsd <= 0 || !is_finite($amountUsd)) {
            throw new \InvalidArgumentException('El pago confirmado de Mercado Pago está incompleto.');
        }

        $payment = new BillingPayment();
        $payment->id_billing_account = $idBillingAccount;
        $payment->provider = BillingPayment::PROVIDER_MERCADOPAGO;
        $payment->status = BillingPayment::STATUS_APPROVED;
        $payment->amount_usd = round($amountUsd, 2);
        $payment->amount_ars = round($amountArs, 2);
        $payment->currency = 'ARS';
        $payment->external_reference = (string) ($confirmedPayment['external_reference'] ?? '');
        $payment->provider_subscription_id = $providerSubscriptionId;
        $payment->provider_payment_id = $providerPaymentId;
        $payment->payload_json = json_encode(
            $confirmedPayment['provider_payload'] ?? [],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
        $payment->paid_at = (string) ($confirmedPayment['paid_at'] ?? date('Y-m-d H:i:s'));
        if (!$payment->save()) {
            throw new \RuntimeException('No se pudo registrar el pago confirmado: ' . json_encode($payment->getErrors()));
        }

        return $payment;
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
        return PricingPesByEncounterClassMetadata::loadConfig();
    }

    /**
     * Validates a public plan selection and returns its server-calculated monthly price.
     *
     * @param array<string, mixed> $plan
     */
    public static function estimatePlanMonthlyUsd(array $plan, string $perfil): float
    {
        return self::estimatePlanMonthlyQuote($plan, $perfil)['amount_usd'];
    }

    /**
     * Validates a public plan selection and returns its server-calculated monthly breakdown.
     *
     * @param array<string, mixed> $plan
     * @return array{
     *     amount_usd: float,
     *     list_amount_usd: float,
     *     discount_amount_usd: float,
     *     discount_percent: float,
     *     total_attentions: int,
     *     items: list<array{code: string, attentions_per_month: int, unit_amount_usd: float, amount_usd: float}>
     * }
     */
    public static function estimatePlanMonthlyQuote(array $plan, string $perfil): array
    {
        $perfil = strtoupper(trim($perfil));
        if (!in_array($perfil, [self::PERFIL_CLINICA, self::PERFIL_CONSULTORIO], true)) {
            throw new \InvalidArgumentException('Perfil de alta inválido (CLINICA o CONSULTORIO).');
        }

        return self::estimateMonthlyQuote(self::normalizePlan($plan, $perfil));
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
        self::assertAllowedCountry($idLocalidad);

        $telefono = trim((string) ($efector['telefono'] ?? ''));
        ContactPhoneValidator::assertValid($telefono, 'Teléfono del efector');

        $publico = $sector === self::SECTOR_PUBLICO;
        $tipologia = $perfil === self::PERFIL_CONSULTORIO
            ? 'CLIN'
            : ($publico ? 'CAP' : 'CLIN');

        return [
            'nombre' => $nombre,
            'domicilio' => $domicilio,
            'telefono' => $telefono !== '' ? $telefono : null,
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
                        'dictado_incluido' => true,
                        'videollamada_permitida' => !empty($plan['videollamada']),
                        'attentions_per_month' => (int) (PricingPesByEncounterClassMetadata::loadConfig()['defaults']['consultorio_attentions'] ?? 200),
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
            $video = $class === 'AMB' && !empty($cfg['videollamada_permitida']);
            $classes[$class] = [
                'max_pes' => $max,
                'attentions_per_month' => $attentions,
                'dictado_incluido' => true,
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
                $classes['AMB']['attentions_per_month'] = (int) (PricingPesByEncounterClassMetadata::loadConfig()['defaults']['consultorio_attentions'] ?? 200);
            }
        }

        return ['classes' => $classes];
    }

    /**
     * @param array{classes: array<string, array{max_pes?: int, attentions_per_month?: int, dictado_incluido: bool, videollamada_permitida: bool}>}|array<string, array{max_pes?: int, attentions_per_month?: int, dictado_incluido: bool, videollamada_permitida: bool}> $plan
     */
    public static function estimateMonthlyUsd(array $plan): float
    {
        return self::estimateMonthlyQuote($plan)['amount_usd'];
    }

    /**
     * @param array<string, mixed> $plan
     * @return array{
     *     amount_usd: float,
     *     list_amount_usd: float,
     *     discount_amount_usd: float,
     *     discount_percent: float,
     *     total_attentions: int,
     *     items: list<array{code: string, attentions_per_month: int, unit_amount_usd: float, amount_usd: float}>
     * }
     */
    private static function estimateMonthlyQuote(array $plan): array
    {
        if (!isset($plan['classes']) || !is_array($plan['classes'])) {
            $plan = ['classes' => $plan];
        }

        $byClass = [];
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
                $video = $video || !empty($cfg['videollamada_permitida']);
            }
        }

        // Dictado siempre incluido en el precio.
        return PricingPesByEncounterClassMetadata::estimateMonthlyQuote($byClass, true, $video);
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
        EnsureAdminEfectorAssignment::ensurePersonaEnEfector(
            $idPersona,
            $idEfector,
            $actingUserId,
            true
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

    /**
     * Valida que la localidad exista y pertenezca a un país habilitado para el alta.
     */
    private static function assertAllowedCountry(int $idLocalidad): void
    {
        $localidad = Localidad::findOne(['id_localidad' => $idLocalidad]);
        if ($localidad === null) {
            throw new \InvalidArgumentException('Localidad inválida.');
        }

        $iso2 = '';
        try {
            $departamento = $localidad->departamento;
            $provincia = $departamento !== null ? $departamento->provincia : null;
            $pais = $provincia !== null ? $provincia->pais : null;
            $iso2 = $pais !== null ? strtoupper(trim((string) $pais->iso2)) : '';
        } catch (\Throwable $e) {
            Yii::warning('No se pudo resolver país de la localidad: ' . $e->getMessage(), 'billing.signup');
        }

        $allowed = (array) (Yii::$app->params['signupAllowedIso2'] ?? ['AR', 'UY']);
        if ($iso2 === '' || !in_array($iso2, $allowed, true)) {
            throw new \InvalidArgumentException('Bioenlace aún no está disponible en ese país.');
        }
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
