<?php

namespace common\components\Domain\Organization\Efector\Application\UseCase;

use common\components\Domain\Organization\Billing\Application\Service\SpaceSignupPaymentNotifier;
use common\models\Organization\BillingAccount;
use common\models\Organization\BillingAccountEfector;
use common\models\Organization\BillingPayment;
use common\models\Organization\BillingSignupRequest;
use common\models\Clinical\EncounterDefinition;
use common\models\Organization\Efector;
use common\models\Organization\ServiciosEfector;
use common\models\Geo\Departamento;
use common\models\Geo\Localidad;
use common\models\Geo\Provincia;
use common\models\Person\Persona;
use common\models\Platform\User;
use common\components\Domain\Organization\Pes\Application\UseCase\EnsureAdminEfectorAssignment;
use common\components\Domain\Organization\Pes\Domain\Catalog\PricingPesByEncounterClassCatalog;
use common\components\Domain\Organization\Efector\Application\Service\ContactPhoneValidator;
use common\components\Domain\Organization\Efector\Application\Service\BillingAccountService;
use common\components\Domain\Geo\Application\Service\GeoLocationResolver;
use common\components\Platform\Core\Product\PricingPesByEncounterClassMetadata;
use Yii;
use yii\db\Query;

/**
 * Alta self-service de espacio de atención + AdminEfector + licencia.
 */
final class SignUpInstitutionalEfector
{
    public const SECTOR_PUBLICO = 'PUBLICO';

    public const SECTOR_PRIVADO = 'PRIVADO';

    public const PERFIL_ESPACIO_ATENCION = 'ESPACIO_ATENCION';

    private const PERFIL_LEGACY = ['CONSULTORIO', 'CLINICA'];

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
        $draft = self::prepareDraftForPersistedCheckout($payload);
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
                $efector = Efector::findBySql(
                    'SELECT * FROM ' . Efector::tableName()
                    . ' WHERE id_efector = :id AND codigo_sisa = :sisa FOR UPDATE',
                    [
                        ':id' => (int) $efectorData['id_efector'],
                        ':sisa' => (string) $efectorData['codigo_sisa'],
                    ]
                )->one();
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
                'notas' => 'Alta self-service de espacio de atención.',
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
                    'max_pes' => $cfg['max_pes'],
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
            $log->notas = 'Alta de espacio de atención con primer pago confirmado por Mercado Pago.';
            $log->id_user = (int) $user->id;
            $log->id_billing_account = (int) $account->id;
            $log->id_efector = $idEfector;
            $log->reviewed_at = date('Y-m-d H:i:s');
            if (!$log->save(false)) {
                throw new \RuntimeException('No se pudo registrar la solicitud de alta comercial.');
            }

            $tx->commit();

            (new SpaceSignupPaymentNotifier())->notifyPaymentConfirmed(
                (string) $user->email,
                (string) $efector->nombre
            );

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
                'next_steps' => self::nextStepsForSpace(),
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
        return self::prepareDraftInternal($payload, false);
    }

    /**
     * Keeps already-persisted legacy checkouts fulfillable without accepting them
     * as new signup requests.
     *
     * @param array<string, mixed> $payload
     * @return array{perfil: string, sector: string, ministerio: ?BillingAccount, efector: array<string, mixed>, plan: array<string, mixed>, amount_usd: float}
     */
    public static function prepareDraftForPersistedCheckout(array $payload): array
    {
        return self::prepareDraftInternal($payload, true);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{perfil: string, sector: string, ministerio: ?BillingAccount, efector: array<string, mixed>, plan: array<string, mixed>, amount_usd: float}
     */
    private static function prepareDraftInternal(array $payload, bool $allowPersistedLegacyProfile): array
    {
        $perfil = strtoupper(trim((string) ($payload['perfil'] ?? self::PERFIL_ESPACIO_ATENCION)));
        $isLegacyConsultorio = $perfil === 'CONSULTORIO';
        $isAllowedProfile = $perfil === self::PERFIL_ESPACIO_ATENCION
            || ($allowPersistedLegacyProfile && in_array($perfil, self::PERFIL_LEGACY, true));
        if (!$isAllowedProfile) {
            throw new \InvalidArgumentException(
                'El alta comercial usa el contrato unificado ESPACIO_ATENCION.'
            );
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

        if ($isLegacyConsultorio && $sector === self::SECTOR_PUBLICO) {
            throw new \InvalidArgumentException(
                'El checkout histórico de consultorio requiere sector privado.'
            );
        }

        if ($sector === self::SECTOR_PUBLICO) {
            $efector = self::resolvePublicEfector($payload['efector']);
            self::assertAllowedCountry((int) $efector['id_localidad']);
        } else {
            $inputEfector = $payload['efector'];
            $hasCoordinates = ($inputEfector['lat'] ?? $inputEfector['latitud'] ?? null) !== null
                || ($inputEfector['lng'] ?? $inputEfector['longitud'] ?? null) !== null;
            if ((int) ($inputEfector['id_localidad'] ?? 0) <= 0 && !$hasCoordinates) {
                throw new \InvalidArgumentException(
                    'Seleccioná una localidad válida o ubicá el efector en el mapa.'
                );
            }
            $efector = self::normalizeEfector(
                $payload['efector'],
                $sector,
                $allowPersistedLegacyProfile ? $perfil : self::PERFIL_ESPACIO_ATENCION
            );
        }
        $plan = self::normalizePlan(
            $payload['plan'] ?? [],
            $allowPersistedLegacyProfile ? $perfil : self::PERFIL_ESPACIO_ATENCION
        );
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

    /**
     * Busca efectores públicos disponibles para seleccionar durante el alta.
     *
     * @return array<int, array{codigo_sisa: string, nombre: string, domicilio: string, localidad: string}>
     */
    public static function searchAvailablePublicEfectores(string $query): array
    {
        $query = trim($query);
        if (strlen($query) < 2 || strlen($query) > 80) {
            return [];
        }

        $term = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $query) . '%';
        $ministerioMemberships = (new Query())
            ->select('membership.id_efector')
            ->from(['membership' => BillingAccountEfector::tableName()])
            ->innerJoin(
                ['account' => BillingAccount::tableName()],
                'account.id = membership.id_billing_account'
            )
            ->where([
                'membership.rol_membresia' => BillingAccountEfector::ROL_AFILIADO,
                'membership.deleted_at' => null,
                'account.tipo' => BillingAccount::TIPO_MINISTERIO,
                'account.deleted_at' => null,
            ]);
        $billingPoolMemberships = (new Query())
            ->select('membership.id_efector')
            ->from(['membership' => BillingAccountEfector::tableName()])
            ->where([
                'membership.rol_membresia' => BillingAccountEfector::ROL_POOL,
                'membership.deleted_at' => null,
            ]);

        return Efector::find()
            ->select([
                'codigo_sisa' => 'efectores.codigo_sisa',
                'nombre' => 'efectores.nombre',
                'domicilio' => 'efectores.domicilio',
                'localidad' => 'localidad.nombre',
            ])
            ->innerJoin(['localidad' => Localidad::tableName()], 'localidad.id_localidad = efectores.id_localidad')
            ->where([
                'or',
                ['like', 'efectores.nombre', $term, false],
                ['like', 'efectores.codigo_sisa', $term, false],
            ])
            ->andWhere([
                'or',
                ['efectores.estado' => null],
                ['<>', 'efectores.estado', 'INACTIVO'],
            ])
            ->andWhere("LOWER(COALESCE(efectores.origen_financiamiento, '')) NOT LIKE :private", [
                ':private' => '%privado%',
            ])
            ->andWhere(['not like', 'efectores.codigo_sisa', 'W%', false])
            ->andWhere(['not like', 'efectores.codigo_sisa', 'DEV%', false])
            ->andWhere([
                'not exists',
                (clone $ministerioMemberships)->andWhere('membership.id_efector = efectores.id_efector'),
            ])
            ->andWhere([
                'not exists',
                (clone $billingPoolMemberships)->andWhere('membership.id_efector = efectores.id_efector'),
            ])
            ->orderBy(['efectores.nombre' => SORT_ASC, 'efectores.codigo_sisa' => SORT_ASC])
            ->limit(20)
            ->asArray()
            ->all();
    }

    /**
     * Centros activos en Bioenlace (licencia vigente o servicios configurados)
     * para que el personal de salud solicite incorporarse desde la app.
     *
     * @return list<array<string, mixed>>
     */
    public static function listAvailableCenters(
        string $query,
        ?string $sector = null,
        ?int $idProvincia = null
    ): array {
        $query = trim($query);
        if (strlen($query) > 80) {
            return [];
        }

        $centers = self::availableCentersQuery();

        if ($query !== '') {
            $term = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $query) . '%';
            $centers->andWhere([
                'or',
                ['like', 'efectores.nombre', $term, false],
                ['like', 'efectores.codigo_sisa', $term, false],
                ['like', 'localidad.nombre', $term, false],
            ]);
        }
        if ($sector !== null && $sector !== '') {
            Efector::applySectorSaludFilterToQuery($centers, $sector);
        }
        if ($idProvincia !== null && $idProvincia > 0) {
            $centers->andWhere(['departamento.id_provincia' => $idProvincia]);
        }

        $rows = $centers
            ->orderBy(['efectores.nombre' => SORT_ASC])
            ->limit(50)
            ->asArray()
            ->all();

        return array_map([self::class, 'mapAvailableCenter'], $rows);
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function findAvailableCenter(int $idEfector): ?array
    {
        if ($idEfector <= 0) {
            return null;
        }

        $row = self::availableCentersQuery()
            ->andWhere(['efectores.id_efector' => $idEfector])
            ->asArray()
            ->one();

        return is_array($row) ? self::mapAvailableCenter($row) : null;
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    private static function availableCentersQuery()
    {
        $memberships = (new Query())
            ->select('membership.id_efector')
            ->from(['membership' => BillingAccountEfector::tableName()])
            ->innerJoin(
                ['account' => BillingAccount::tableName()],
                'account.id = membership.id_billing_account'
            )
            ->where([
                'membership.deleted_at' => null,
                'account.deleted_at' => null,
            ])
            ->andWhere([
                'or',
                ['account.activo' => 1],
                ['membership.rol_membresia' => BillingAccountEfector::ROL_AFILIADO],
            ]);

        $services = (new Query())
            ->select('servicio.id_efector')
            ->from(['servicio' => ServiciosEfector::tableName()]);

        return Efector::find()
            ->select([
                'id_efector' => 'efectores.id_efector',
                'nombre' => 'efectores.nombre',
                'codigo_sisa' => 'efectores.codigo_sisa',
                'localidad' => 'localidad.nombre',
                'departamento' => 'departamento.nombre',
                'provincia' => 'provincia.nombre',
                'id_provincia' => 'provincia.id_provincia',
                'origen_financiamiento' => 'efectores.origen_financiamiento',
            ])
            ->innerJoin(
                ['localidad' => Localidad::tableName()],
                'localidad.id_localidad = efectores.id_localidad'
            )
            ->innerJoin(
                ['departamento' => Departamento::tableName()],
                'departamento.id_departamento = localidad.id_departamento'
            )
            ->innerJoin(
                ['provincia' => Provincia::tableName()],
                'provincia.id_provincia = departamento.id_provincia'
            )
            ->where([
                'or',
                ['efectores.estado' => null],
                ['<>', 'efectores.estado', 'INACTIVO'],
            ])
            ->andWhere([
                'or',
                ['exists', (clone $memberships)->andWhere('membership.id_efector = efectores.id_efector')],
                ['exists', (clone $services)->andWhere('servicio.id_efector = efectores.id_efector')],
            ]);
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private static function mapAvailableCenter(array $row): array
    {
        $origen = (string) ($row['origen_financiamiento'] ?? '');

        return [
            'id_efector' => (int) ($row['id_efector'] ?? 0),
            'nombre' => (string) ($row['nombre'] ?? ''),
            'codigo_sisa' => isset($row['codigo_sisa']) ? (string) $row['codigo_sisa'] : null,
            'localidad' => isset($row['localidad']) ? (string) $row['localidad'] : null,
            'departamento' => isset($row['departamento']) ? (string) $row['departamento'] : null,
            'provincia' => isset($row['provincia']) ? (string) $row['provincia'] : null,
            'id_provincia' => isset($row['id_provincia']) ? (int) $row['id_provincia'] : null,
            'sector' => stripos($origen, 'privado') !== false
                ? self::SECTOR_PRIVADO
                : self::SECTOR_PUBLICO,
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
        $telefono = ContactPhoneValidator::normalizeMobile((string) ($payload['contact_phone'] ?? '')) ?? '';
        ContactPhoneValidator::assertValidMobile($telefono, 'Teléfono del administrador');

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
    public static function nextStepsForSpace(): array
    {
        return [
            'Ingresá a la web clínica con tu usuario y contraseña.',
            'Desde administración del espacio podés habilitar servicios y asignar personal.',
            'La asignación clínica es independiente del rol de administración.',
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
    public static function estimatePlanMonthlyUsd(array $plan): float
    {
        return self::estimatePlanMonthlyQuote($plan)['amount_usd'];
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
    public static function estimatePlanMonthlyQuote(array $plan): array
    {
        return self::estimateMonthlyQuote(self::normalizePlan($plan));
    }

    /**
     * @param array<string, mixed> $efector
     * @return array{nombre: string, domicilio: string, telefono: ?string, id_localidad: int, origen_financiamiento: string, dependencia: string, tipologia: string, codigo_sisa: string, latitud: ?float, longitud: ?float}
     */
    private static function normalizeEfector(
        array $efector,
        string $sector,
        string $perfil = self::PERFIL_ESPACIO_ATENCION
    ): array
    {
        $nombre = trim((string) ($efector['nombre'] ?? ''));
        $domicilio = trim((string) ($efector['domicilio'] ?? ''));
        if ($nombre === '' || $domicilio === '') {
            throw new \InvalidArgumentException('Indicá nombre y domicilio del efectores.');
        }

        [$latitud, $longitud] = self::normalizeCoordinates(
            $efector['lat'] ?? $efector['latitud'] ?? null,
            $efector['lng'] ?? $efector['longitud'] ?? null
        );

        $idLocalidad = (int) ($efector['id_localidad'] ?? 0);
        if ($idLocalidad <= 0 && $latitud !== null && $longitud !== null) {
            // El mapa del alta puede no traer id_localidad: la resolvemos nosotros.
            $idLocalidad = (int) (GeoLocationResolver::nearestLocalityId($latitud, $longitud) ?? 0);
        }
        if ($idLocalidad <= 0) {
            $idLocalidad = self::resolveDefaultLocalidadId();
        }
        self::assertAllowedCountry($idLocalidad);

        $telefono = trim((string) ($efector['telefono'] ?? ''));
        ContactPhoneValidator::assertValid($telefono, 'Teléfono del efector');

        $publico = $sector === self::SECTOR_PUBLICO;
        $tipologia = $perfil === 'CONSULTORIO' ? 'CLIN' : ($publico ? 'CAP' : 'CLIN');

        return [
            'nombre' => $nombre,
            'domicilio' => $domicilio,
            'telefono' => $telefono !== '' ? $telefono : null,
            'id_localidad' => $idLocalidad,
            'origen_financiamiento' => $publico ? 'Público' : 'Privado',
            'dependencia' => $publico ? 'Provincial' : 'Privado',
            'tipologia' => $tipologia,
            'codigo_sisa' => self::generateCodigoSisa(),
            'latitud' => $latitud,
            'longitud' => $longitud,
        ];
    }

    /**
     * @return array{0: ?float, 1: ?float}
     */
    private static function normalizeCoordinates(mixed $latitud, mixed $longitud): array
    {
        $hasLat = $latitud !== null && $latitud !== '';
        $hasLng = $longitud !== null && $longitud !== '';
        if (!$hasLat && !$hasLng) {
            return [null, null];
        }
        if (!$hasLat || !$hasLng) {
            throw new \InvalidArgumentException('Indicá latitud y longitud juntas.');
        }
        if (!is_numeric($latitud) || !is_numeric($longitud)) {
            throw new \InvalidArgumentException('Las coordenadas del efector son inválidas.');
        }

        $lat = round((float) $latitud, 7);
        $lng = round((float) $longitud, 7);
        if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
            throw new \InvalidArgumentException('Las coordenadas del efector están fuera de rango.');
        }

        return [$lat, $lng];
    }

    /**
     * @param array<string, mixed> $plan
     * @return array{classes: array<string, array<string, mixed>>}
     */
    private static function normalizePlan(
        array $plan,
        string $perfil = self::PERFIL_ESPACIO_ATENCION
    ): array
    {
        $raw = $plan['classes'] ?? null;
        if (!is_array($raw) || $raw === []) {
            if ($perfil === 'CONSULTORIO') {
                $raw = [
                    'AMB' => [
                        'attentions_per_month' => 200,
                        'max_pes' => 1,
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
            if ($attentions <= 0 && in_array($perfil, self::PERFIL_LEGACY, true)) {
                $legacyMaxPes = (int) ($cfg['max_pes'] ?? 0);
                if ($legacyMaxPes > 0) {
                    $attentions = $legacyMaxPes
                        * (int) PricingPesByEncounterClassMetadata::referenceEncountersPerMonth($class);
                }
            }
            if ($attentions <= 0) {
                continue;
            }
            $maxPes = null;
            if (in_array($perfil, self::PERFIL_LEGACY, true)) {
                $maxPes = (int) ($cfg['max_pes'] ?? 0);
                if ($maxPes <= 0) {
                    $maxPes = PricingPesByEncounterClassMetadata::deriveMaxPesFromAttentions($attentions, $class);
                }
                $maxPes = max(1, $maxPes);
            }
            $row = [
                'max_pes' => $maxPes,
                'attentions_per_month' => $attentions,
            ];
            foreach (PricingPesByEncounterClassMetadata::addonPlanFields() as $addonId => $field) {
                $state = PricingPesByEncounterClassMetadata::addonState($addonId, $class);
                $row[$field] = $state === PricingPesByEncounterClassCatalog::ADDON_INCLUDED
                    || ($state === PricingPesByEncounterClassCatalog::ADDON_OPTIONAL
                        && !empty($cfg[$field]));
            }
            $classes[$class] = $row;
        }
        if ($perfil === 'CONSULTORIO') {
            if (!isset($classes['AMB']) || isset($classes['EMER']) || isset($classes['IMP'])) {
                throw new \InvalidArgumentException(
                    'El checkout histórico de consultorio requiere solo atención ambulatoria.'
                );
            }
            $classes['AMB']['max_pes'] = 1;
        }
        if ($classes === []) {
            throw new \InvalidArgumentException('Elegí al menos un tipo de atención (AMB / EMER / IMP).');
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
        $selectedAddons = [];
        foreach ($plan['classes'] as $class => $cfg) {
            if (!is_array($cfg)) {
                continue;
            }
            $attentions = (int) ($cfg['attentions_per_month'] ?? 0);
            if ($attentions <= 0) {
                $maxPes = (int) ($cfg['max_pes'] ?? 0);
                $ref = (int) PricingPesByEncounterClassMetadata::referenceEncountersPerMonth((string) $class);
                $attentions = $maxPes > 0 ? $maxPes * $ref : 0;
            }
            if ($attentions <= 0) {
                continue;
            }
            $code = (string) $class;
            $byClass[$code] = $attentions;
            foreach (PricingPesByEncounterClassMetadata::addonPlanFields() as $addonId => $field) {
                if (empty($cfg[$field])) {
                    continue;
                }
                if (PricingPesByEncounterClassMetadata::addonState($addonId, $code)
                    === PricingPesByEncounterClassCatalog::ADDON_OPTIONAL
                ) {
                    $selectedAddons[$addonId] = true;
                }
            }
        }

        return PricingPesByEncounterClassMetadata::estimateMonthlyQuote($byClass, $selectedAddons);
    }

    /**
     * @param array{nombre: string, domicilio: string, telefono: ?string, id_localidad: int, origen_financiamiento: string, dependencia: string, tipologia: string, codigo_sisa: string, latitud?: ?float, longitud?: ?float} $data
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
        if (isset($data['latitud'], $data['longitud'])
            && $data['latitud'] !== null
            && $data['longitud'] !== null) {
            $efector->latitud = (string) $data['latitud'];
            $efector->longitud = (string) $data['longitud'];
        }
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
