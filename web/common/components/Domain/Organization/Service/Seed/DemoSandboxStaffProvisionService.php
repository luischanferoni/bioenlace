<?php

namespace common\components\Domain\Organization\Service\Seed;

use common\components\Domain\Organization\Service\ProfesionalCobertura\ProfesionalCoberturaService;
use common\components\Domain\Organization\Service\ProfesionalEfectorServicio\ProfesionalEfectorServicioAltaService;
use common\components\Domain\Person\Util\CuilValidator;
use common\models\Efector;
use common\models\Person\Persona;
use common\models\ProfesionalEfectorServicioAgenda;
use common\models\Servicio;
use common\models\ServiciosEfector;
use common\models\User;
use Yii;

/**
 * Provisiona un médico efímero (Persona+User+PES+agenda) en el efector demo.
 *
 * Identidades reservadas: documento 38xxxxxx, username demo_m_* / demo_e_* / demo_a_*.
 */
final class DemoSandboxStaffProvisionService
{
    public const SEED_MARKER = 'seed:demo-sandbox-staff';

    public const DOCUMENTO_PREFIX = '38';

    private const HORARIO_LABORAL = '8,9,10,11,12,13,14,15,16,17';

    /**
     * @return array{
     *     id_user: int,
     *     id_persona: int,
     *     id_pes: int,
     *     id_servicio: int,
     *     id_efector: int,
     *     id_agenda: int|null,
     *     username: string,
     *     documento: string
     * }
     * @param array{
     *     rbac_role?: string,
     *     apellido?: string,
     *     username_prefix?: string,
     *     cobertura_classes?: list<string>,
     *     cobertura_ttl_seconds?: int
     * } $options
     */
    public function provision(
        int $idEfector,
        string $servicioNombre = 'MED GENERAL',
        bool $withAgenda = true,
        array $options = []
    ): array
    {
        if ($idEfector <= 0) {
            throw new \InvalidArgumentException('id_efector inválido.');
        }
        $efector = Efector::findOne($idEfector);
        if ($efector === null) {
            throw new \InvalidArgumentException("No existe efectores.id_efector={$idEfector}.");
        }
        // Nunca provisionar sandbox en un centro real (p. ej. 863).
        $codigoSisa = trim((string) $efector->codigo_sisa);
        if ($idEfector === EfectorDemoSeedService::defaultEfectorRefId() || !str_starts_with($codigoSisa, 'DEV')) {
            throw new \InvalidArgumentException(
                "Sandbox solo en plantilla DEV (recibido id_efector={$idEfector}, codigo_sisa={$codigoSisa})."
            );
        }

        $servicio = Servicio::find()->where(['nombre' => $servicioNombre])->one();
        if ($servicio === null && strcasecmp($servicioNombre, 'ENFERMERIA') === 0) {
            $servicio = Servicio::find()->where(['item_name' => 'enfermeria'])->one();
        }
        if ($servicio === null) {
            throw new \InvalidArgumentException('Servicio "' . $servicioNombre . '" no encontrado.');
        }
        $idServicio = (int) $servicio->id_servicio;

        $usernamePrefix = trim((string) ($options['username_prefix'] ?? 'demo_m_'));
        if ($usernamePrefix === '') {
            $usernamePrefix = 'demo_m_';
        }
        [$documento, $username] = $this->allocateIdentity($usernamePrefix);
        $password = bin2hex(random_bytes(16));

        $db = Yii::$app->db;
        $tx = $db->beginTransaction();
        try {
            [$persona, $user] = $this->createPersonaUser(
                $documento,
                $username,
                $password,
                (string) ($options['apellido'] ?? 'Médico')
            );
            $actingUserId = (int) $user->id;

            $this->ensureServicioEnEfector($idServicio, $idEfector, $actingUserId);

            $pesResult = ProfesionalEfectorServicioAltaService::ensurePersonaServicioEnEfector(
                (int) $persona->id_persona,
                $idEfector,
                $idServicio,
                $actingUserId
            );
            $idPes = (int) $pesResult['id_profesional_efector_servicio'];

            $idAgenda = null;
            if ($withAgenda) {
                $idAgenda = $this->ensureAgenda($idPes, $idEfector, $actingUserId);
            }

            $rbacRole = trim((string) ($options['rbac_role'] ?? ''));
            if ($rbacRole !== '') {
                User::assignRole((int) $user->id, $rbacRole);
            }
            $this->ensureCobertura(
                (int) $persona->id_persona,
                $idEfector,
                $idServicio,
                $idPes,
                $options['cobertura_classes'] ?? [],
                (int) ($options['cobertura_ttl_seconds'] ?? 14400)
            );

            $tx->commit();
        } catch (\Throwable $e) {
            if ($tx->isActive) {
                $tx->rollBack();
            }
            throw $e;
        }

        return [
            'id_user' => (int) $user->id,
            'id_persona' => (int) $persona->id_persona,
            'id_pes' => $idPes,
            'id_servicio' => $idServicio,
            'id_efector' => $idEfector,
            'id_agenda' => $idAgenda,
            'username' => $username,
            'documento' => $documento,
        ];
    }

    /**
     * @return array{0: string, 1: string} documento, username
     */
    private function allocateIdentity(string $usernamePrefix = 'demo_m_'): array
    {
        for ($i = 0; $i < 12; $i++) {
            $suffix = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $documento = self::DOCUMENTO_PREFIX . $suffix;
            if (Persona::find()->where(['documento' => $documento])->exists()) {
                continue;
            }
            $token = bin2hex(random_bytes(4));
            $username = $usernamePrefix . $token;
            if (User::find()->where(['username' => $username])->exists()) {
                continue;
            }

            return [$documento, $username];
        }

        throw new \RuntimeException('No se pudo asignar identidad única para el médico demo.');
    }

    /**
     * @return array{0: Persona, 1: User}
     */
    private function createPersonaUser(
        string $documento,
        string $username,
        string $password,
        string $apellido = 'Médico'
    ): array {
        $persona = new Persona();
        $persona->scenario = Persona::SCENARIOCREATEUPDATE;
        $persona->nombre = 'Demo';
        $persona->apellido = $apellido !== '' ? $apellido : 'Médico';
        $persona->documento = $documento;
        $persona->fecha_nacimiento = '1988-06-12';
        $persona->id_tipodoc = 1;
        $persona->id_estado_civil = 1;
        $persona->acredita_identidad = 1;
        $persona->sexo_biologico = 1;
        $persona->genero = 1;

        if (!$persona->save()) {
            throw new \RuntimeException('Persona demo: ' . json_encode($persona->getErrors()));
        }
        $persona->cuil = CuilValidator::buildFromDni($documento);
        $persona->save(false, ['cuil']);

        $user = new User();
        $user->username = $username;
        $user->email = $username . '@demo.bioenlace.local';
        $user->status = User::STATUS_ACTIVE;
        $user->setPassword($password);
        $user->generateAuthKey();
        if (!$user->save()) {
            throw new \RuntimeException('User demo: ' . json_encode($user->getErrors()));
        }

        $persona->id_user = (int) $user->id;
        $persona->scenario = Persona::SCENARIOUSERUPDATE;
        if (!$persona->save(false)) {
            throw new \RuntimeException('Vincular user demo: ' . json_encode($persona->getErrors()));
        }

        return [$persona, $user];
    }

    /**
     * @param list<string> $classes
     */
    private function ensureCobertura(
        int $idPersona,
        int $idEfector,
        int $idServicio,
        int $idPes,
        array $classes,
        int $ttlSeconds
    ): void {
        if ($classes === []) {
            return;
        }
        $ttlSeconds = max(600, $ttlSeconds);
        $inicio = date('Y-m-d H:i:s', time() - 60);
        $fin = date('Y-m-d H:i:s', time() + $ttlSeconds);
        foreach ($classes as $class) {
            $class = trim((string) $class);
            if ($class === '') {
                continue;
            }
            $result = ProfesionalCoberturaService::crear([
                'id_persona' => $idPersona,
                'id_efector' => $idEfector,
                'id_servicio' => $idServicio,
                'id_profesional_efector_servicio' => $idPes,
                'encounter_class' => $class,
                'inicio' => $inicio,
                'fin' => $fin,
                'rol' => 'demo',
                'notas' => self::SEED_MARKER,
            ]);
            if (empty($result['ok'])) {
                Yii::warning(
                    'Demo cobertura no creada class=' . $class . ' ' . json_encode($result['errors'] ?? []),
                    __METHOD__
                );
            }
        }
    }

    private function ensureServicioEnEfector(int $idServicio, int $idEfector, int $actingUserId): void
    {
        $exists = ServiciosEfector::findActive()
            ->where(['id_servicio' => $idServicio, 'id_efector' => $idEfector])
            ->exists();
        if ($exists) {
            return;
        }

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

    private function ensureAgenda(int $idPes, int $idEfector, int $actingUserId): int
    {
        $existing = ProfesionalEfectorServicioAgenda::find()
            ->where(['id_profesional_efector_servicio' => $idPes, 'deleted_at' => null])
            ->one();
        if ($existing !== null) {
            return (int) $existing->id;
        }

        $agenda = new ProfesionalEfectorServicioAgenda();
        $agenda->id_profesional_efector_servicio = $idPes;
        $agenda->id_efector = $idEfector;
        $agenda->encounter_class = \common\models\Clinical\Encounter::ENCOUNTER_CLASS_AMB;
        $agenda->formas_atencion = 'SIN_ATENCION';
        $agenda->duracion_slot_minutos = 15;
        $agenda->intervalo_minutos = 15;
        $agenda->acepta_consultas_online = 0;
        $agenda->lunes_2 = self::HORARIO_LABORAL;
        $agenda->martes_2 = self::HORARIO_LABORAL;
        $agenda->miercoles_2 = self::HORARIO_LABORAL;
        $agenda->jueves_2 = self::HORARIO_LABORAL;
        $agenda->viernes_2 = self::HORARIO_LABORAL;
        $agenda->sabado_2 = '';
        $agenda->domingo_2 = '';

        ActiveRecordConsoleBlame::save($agenda, $actingUserId, 'Agenda demo sandbox');

        return (int) $agenda->id;
    }
}
