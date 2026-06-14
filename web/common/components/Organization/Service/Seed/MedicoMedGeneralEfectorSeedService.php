<?php

namespace common\components\Organization\Service\Seed;

use common\components\Organization\Service\ProfesionalEfectorServicio\ProfesionalEfectorServicioAltaService;
use common\models\Efector;
use common\models\Person\Persona;
use common\models\ProfesionalEfectorServicio;
use common\models\ProfesionalEfectorServicioAgenda;
use common\models\Servicio;
use common\models\ServiciosEfector;
use common\models\User;
use Yii;
use yii\db\Query;

/**
 * Seed de desarrollo: médico en MED GENERAL para un efector dado (por defecto 863).
 *
 * Idempotente: reutiliza persona/PES si ya existen con el documento reservado.
 */
final class MedicoMedGeneralEfectorSeedService
{
    public const SEED_MARKER = 'seed:medico-med-general-efector';

    /** Documento reservado (sufijo = id efector por defecto). */
    public const DOCUMENTO_EFECTOR_863 = '39999863';

    public const USERNAME_EFECTOR_863 = 'medico_med_general_863';

    public const DEFAULT_PASSWORD_EFECTOR_863 = 'MedGeneral863!';

    public const SERVICIO_NOMBRE = 'MED GENERAL';

    private const HORARIO_LABORAL = '8,9,10,11,12,13,14,15,16,17';

    /**
     * @return array{
     *     ok: bool,
     *     message: string,
     *     created_persona: bool,
     *     created_user: bool,
     *     created_servicio_efector: bool,
     *     created_pes: bool,
     *     created_agenda: bool,
     *     id_efector: int,
     *     id_servicio: int,
     *     id_persona: int,
     *     id_user: int,
     *     id_pes: int,
     *     username: string,
     *     password: string,
     *     documento: string
     * }
     */
    public function upsert(int $idEfector = 863, bool $withAgenda = true, ?string $password = null): array
    {
        if ($idEfector <= 0) {
            throw new \InvalidArgumentException('id_efector inválido.');
        }

        if (Efector::findOne($idEfector) === null) {
            throw new \InvalidArgumentException("No existe efectores.id_efector={$idEfector}.");
        }

        $servicio = Servicio::find()->where(['nombre' => self::SERVICIO_NOMBRE])->one();
        if ($servicio === null) {
            throw new \InvalidArgumentException('Servicio "' . self::SERVICIO_NOMBRE . '" no encontrado en servicios.');
        }
        $idServicio = (int) $servicio->id_servicio;

        [$documento, $username] = $this->resolveIdentityForEfector($idEfector);
        $plainPassword = $password !== null && $password !== ''
            ? $password
            : ($idEfector === 863 ? self::DEFAULT_PASSWORD_EFECTOR_863 : 'MedGeneral' . $idEfector . '!');

        $createdPersona = false;
        $createdUser = false;

        $persona = Persona::findOne(['documento' => $documento]);
        if ($persona === null) {
            [$persona, $user, $createdPersona, $createdUser] = $this->createPersonaUser(
                $documento,
                $username,
                $plainPassword,
                $idEfector
            );
        } else {
            $user = $persona->id_user > 0 ? User::findOne((int) $persona->id_user) : null;
            if ($user === null) {
                [$persona, $user, , $createdUser] = $this->attachUserToPersona($persona, $username, $plainPassword);
            }
        }

        if ($user === null) {
            throw new \RuntimeException('No se pudo resolver usuario para el médico seed.');
        }

        $actingUserId = $this->resolveActingUserId($user, $username);

        $createdServicioEfector = $this->ensureServicioEnEfector($idServicio, $idEfector, $actingUserId);

        $pesBefore = ProfesionalEfectorServicio::findOneActivoPorPersonaEfectorServicio(
            (int) $persona->id_persona,
            $idEfector,
            $idServicio
        );
        $pesResult = ProfesionalEfectorServicioAltaService::ensurePersonaServicioEnEfector(
            (int) $persona->id_persona,
            $idEfector,
            $idServicio,
            $actingUserId
        );
        $idPes = (int) $pesResult['id_profesional_efector_servicio'];
        $createdPes = $pesBefore === null;

        $createdAgenda = false;
        if ($withAgenda) {
            $createdAgenda = $this->ensureAgenda($idPes, $idEfector, $actingUserId);
        }

        return [
            'ok' => true,
            'message' => $createdPes || $createdPersona
                ? 'Médico MED GENERAL creado o completado.'
                : 'Médico MED GENERAL ya existía; datos verificados.',
            'created_persona' => $createdPersona,
            'created_user' => $createdUser,
            'created_servicio_efector' => $createdServicioEfector,
            'created_pes' => $createdPes,
            'created_agenda' => $createdAgenda,
            'id_efector' => $idEfector,
            'id_servicio' => $idServicio,
            'id_persona' => (int) $persona->id_persona,
            'id_user' => (int) $user->id,
            'id_pes' => $idPes,
            'username' => (string) $user->username,
            'password' => $createdUser ? $plainPassword : '(sin cambios — usuario ya existía)',
            'documento' => $documento,
        ];
    }

    /**
     * Elimina PES, agenda y persona/usuario solo si coinciden con el documento seed del efector.
     */
    public function remove(int $idEfector = 863): bool
    {
        [$documento] = $this->resolveIdentityForEfector($idEfector);
        $persona = Persona::findOne(['documento' => $documento]);
        if ($persona === null) {
            return false;
        }

        $servicio = Servicio::find()->where(['nombre' => self::SERVICIO_NOMBRE])->one();
        if ($servicio === null) {
            return false;
        }

        $pes = ProfesionalEfectorServicio::findOneActivoPorPersonaEfectorServicio(
            (int) $persona->id_persona,
            $idEfector,
            (int) $servicio->id_servicio
        );
        if ($pes !== null) {
            $agenda = ProfesionalEfectorServicioAgenda::find()
                ->where(['id_profesional_efector_servicio' => (int) $pes->id, 'deleted_at' => null])
                ->one();
            if ($agenda !== null) {
                $agenda->delete();
            }
            $pes->delete();
        }

        return true;
    }

    /**
     * @return array{documento: string, username: string}
     */
    public static function expectedIdentity(int $idEfector): array
    {
        if ($idEfector === 863) {
            return [
                'documento' => self::DOCUMENTO_EFECTOR_863,
                'username' => self::USERNAME_EFECTOR_863,
            ];
        }

        return [
            'documento' => '39999' . str_pad((string) $idEfector, 3, '0', STR_PAD_LEFT),
            'username' => 'medico_med_general_' . $idEfector,
        ];
    }

    /**
     * @return array{0: string, 1: string} documento, username
     */
    private function resolveIdentityForEfector(int $idEfector): array
    {
        $identity = self::expectedIdentity($idEfector);

        return [$identity['documento'], $identity['username']];
    }

    /**
     * @return array{0: Persona, 1: User, 2: bool, 3: bool}
     */
    private function createPersonaUser(string $documento, string $username, string $password, int $idEfector): array
    {
        $db = Yii::$app->db;
        $tx = $db->beginTransaction();
        try {
            $persona = new Persona();
            $persona->scenario = Persona::SCENARIOCREATEUPDATE;
            $persona->nombre = 'Medico';
            $persona->apellido = 'General Demo';
            $persona->documento = $documento;
            $persona->fecha_nacimiento = '1985-03-15';
            $persona->id_tipodoc = 1;
            $persona->id_estado_civil = 1;
            $persona->acredita_identidad = 1;
            $persona->sexo_biologico = 1;
            $persona->genero = 1;

            if (!$persona->save()) {
                throw new \RuntimeException('Persona seed: ' . json_encode($persona->getErrors()));
            }

            $user = new User();
            $user->username = $username;
            $user->email = $username . '@example.dev';
            $user->status = User::STATUS_ACTIVE;
            $user->setPassword($password);
            $user->generateAuthKey();

            if (!$user->save()) {
                throw new \RuntimeException('User seed: ' . json_encode($user->getErrors()));
            }

            $persona->id_user = (int) $user->id;
            $persona->scenario = Persona::SCENARIOUSERUPDATE;
            if (!$persona->save(false)) {
                throw new \RuntimeException('Vincular user a persona: ' . json_encode($persona->getErrors()));
            }

            $tx->commit();

            return [$persona, $user, true, true];
        } catch (\Throwable $e) {
            if ($tx->isActive) {
                $tx->rollBack();
            }
            throw $e;
        }
    }

    /**
     * @return array{0: Persona, 1: User, 2: bool, 3: bool}
     */
    private function attachUserToPersona(Persona $persona, string $username, string $password): array
    {
        $existing = User::findOne(['username' => $username]);
        if ($existing !== null && (int) $existing->id !== (int) $persona->id_user) {
            throw new \RuntimeException("Username {$username} ya existe para otro usuario.");
        }

        $user = $existing ?? new User();
        $createdUser = $user->isNewRecord;
        if ($createdUser) {
            $user->username = $username;
            $user->email = $username . '@example.dev';
            $user->status = User::STATUS_ACTIVE;
            $user->setPassword($password);
            $user->generateAuthKey();
            if (!$user->save()) {
                throw new \RuntimeException('User seed: ' . json_encode($user->getErrors()));
            }
        }

        $persona->id_user = (int) $user->id;
        $persona->scenario = Persona::SCENARIOUSERUPDATE;
        if (!$persona->save(false)) {
            throw new \RuntimeException('Vincular user a persona: ' . json_encode($persona->getErrors()));
        }

        return [$persona, $user, false, $createdUser];
    }

    private function resolveActingUserId(User $user, string $username): int
    {
        $id = (int) $user->getPrimaryKey();
        if ($id <= 0) {
            $id = (int) $user->getAttribute('id');
        }
        if ($id <= 0) {
            $id = (int) User::find()->select('id')->where(['username' => $username])->scalar();
        }
        if ($id <= 0) {
            throw new \RuntimeException(
                'No se pudo resolver id del usuario seed (' . $username . ') para created_by.'
            );
        }

        return $id;
    }

    private function ensureServicioEnEfector(int $idServicio, int $idEfector, int $actingUserId): bool
    {
        $exists = ServiciosEfector::findActive()
            ->where(['id_servicio' => $idServicio, 'id_efector' => $idEfector])
            ->exists();
        if ($exists) {
            return false;
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

        return true;
    }

    private function ensureAgenda(int $idPes, int $idEfector, int $actingUserId): bool
    {
        $existing = ProfesionalEfectorServicioAgenda::find()
            ->where(['id_profesional_efector_servicio' => $idPes, 'deleted_at' => null])
            ->one();
        if ($existing !== null) {
            return false;
        }

        $agenda = new ProfesionalEfectorServicioAgenda();
        $agenda->id_profesional_efector_servicio = $idPes;
        $agenda->id_efector = $idEfector;
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

        ActiveRecordConsoleBlame::save($agenda, $actingUserId, 'Agenda seed');

        return true;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findSeedRow(int $idEfector = 863): ?array
    {
        [$documento] = $this->resolveIdentityForEfector($idEfector);
        $persona = (new Query())
            ->from('{{%personas}}')
            ->where(['documento' => $documento])
            ->one();
        if ($persona === false) {
            return null;
        }

        $servicioId = (new Query())
            ->select('id_servicio')
            ->from('{{%servicios}}')
            ->where(['nombre' => self::SERVICIO_NOMBRE])
            ->scalar();

        $pes = null;
        if ($servicioId !== false) {
            $pes = (new Query())
                ->from('{{%profesional_efector_servicio}}')
                ->where([
                    'id_persona' => $persona['id_persona'],
                    'id_efector' => $idEfector,
                    'id_servicio' => (int) $servicioId,
                    'deleted_at' => null,
                ])
                ->one();
        }

        return [
            'persona' => $persona,
            'pes' => $pes ?: null,
            'documento' => $documento,
        ];
    }
}
