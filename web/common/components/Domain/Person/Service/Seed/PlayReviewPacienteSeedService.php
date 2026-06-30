<?php

namespace common\components\Domain\Person\Service\Seed;

use common\models\Person\Persona;
use common\models\User;
use common\models\rbac\AuthRole;
use Yii;

/**
 * Cuenta paciente fija para revisión de tiendas (Google Play / App Store).
 *
 * Idempotente: si ya existe el documento reservado, actualiza contraseña si se indica.
 */
final class PlayReviewPacienteSeedService
{
    public const DOCUMENTO = '39999001';
    public const USERNAME = 'play_review_paciente';
    public const DEFAULT_PASSWORD = 'PlayReviewPaciente1!';

    /**
     * @return array<string, mixed>
     */
    public function upsert(?string $plainPassword = null): array
    {
        $password = trim((string) ($plainPassword ?? '')) !== ''
            ? (string) $plainPassword
            : self::DEFAULT_PASSWORD;

        $persona = Persona::findOne(['documento' => self::DOCUMENTO]);
        $createdPersona = false;
        $createdUser = false;

        if ($persona === null) {
            $persona = new Persona();
            $persona->scenario = Persona::SCENARIOCREATEUPDATE;
            $persona->nombre = 'Revisión';
            $persona->apellido = 'Play Store';
            $persona->documento = self::DOCUMENTO;
            $persona->fecha_nacimiento = '1990-06-15';
            $persona->id_tipodoc = 1;
            $persona->id_estado_civil = 1;
            $persona->acredita_identidad = 1;
            $persona->sexo_biologico = 1;
            $persona->genero = 1;
            if (!$persona->save()) {
                throw new \RuntimeException('No se pudo crear persona play review: ' . json_encode($persona->getErrors()));
            }
            $createdPersona = true;
        }

        $user = null;
        if ($persona->id_user) {
            $user = User::findOne((int) $persona->id_user);
        }
        if ($user === null) {
            $user = User::findOne(['username' => self::USERNAME]);
        }

        if ($user === null) {
            $user = new User();
            $user->username = self::USERNAME;
            $user->email = 'play_review_paciente@example.com';
            $user->status = User::STATUS_ACTIVE;
            $user->generateAuthKey();
            $createdUser = true;
        }

        $user->setPassword($password);
        if (!$user->save()) {
            throw new \RuntimeException('No se pudo guardar usuario play review: ' . json_encode($user->getErrors()));
        }

        if ((int) $persona->id_user !== (int) $user->id) {
            $persona->id_user = (int) $user->id;
            $persona->scenario = Persona::SCENARIOUSERUPDATE;
            $persona->save(false);
        }

        $this->ensurePacienteRole((int) $user->id);

        return [
            'ok' => true,
            'created_persona' => $createdPersona,
            'created_user' => $createdUser,
            'id_persona' => (int) $persona->id_persona,
            'id_user' => (int) $user->id,
            'username' => self::USERNAME,
            'password' => $password,
            'documento' => self::DOCUMENTO,
        ];
    }

    private function ensurePacienteRole(int $userId): void
    {
        try {
            if (class_exists(\common\models\BioenlaceDbManager::class)
                && method_exists(\common\models\BioenlaceDbManager::class, 'asignarRolPacienteSiNoExiste')
            ) {
                \common\models\BioenlaceDbManager::asignarRolPacienteSiNoExiste($userId);

                return;
            }
            $pacienteRole = AuthRole::findOne(['name' => 'paciente']);
            if ($pacienteRole && Yii::$app->has('authManager', true)) {
                $am = Yii::$app->authManager;
                if (!$am->getAssignment('paciente', $userId)) {
                    $am->assign($pacienteRole, $userId);
                }
            }
        } catch (\Throwable $e) {
            Yii::warning('PlayReviewPacienteSeed: rol paciente: ' . $e->getMessage(), __METHOD__);
        }
    }
}
