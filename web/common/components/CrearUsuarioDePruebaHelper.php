<?php

namespace common\components;

use Yii;
use common\models\Persona;
use common\models\User;
use webvimark\modules\UserManagement\models\rbacDB\Role;

/**
 * Alta única de persona + usuario de prueba (sin Didit ni MPI). No actualiza registros existentes.
 */
final class CrearUsuarioDePruebaHelper
{
    /** Documento reservado para entornos de desarrollo; cambiar si ya existe en tu base. */
    public const DOCUMENTO = '39999901';
    public const NOMBRE = 'Admin';
    public const APELLIDO = 'Efector';

    /**
     * @return array{ok:bool, message:string, persona?:array, user?:array|null, errors?:mixed}
     */
    public static function crear(): array
    {
        $dni = self::DOCUMENTO;
        $username = 'usr_prueba_' . $dni;

        if (Persona::findOne(['documento' => $dni]) !== null) {
            return [
                'ok' => false,
                'message' => 'Ya existe una persona con el documento de prueba (' . $dni . '). No se modifica nada.',
            ];
        }

        if (User::findOne(['username' => $username]) !== null) {
            return [
                'ok' => false,
                'message' => 'Ya existe un usuario con el nombre de usuario de prueba (' . $username . '). No se modifica nada.',
            ];
        }

        $db = Yii::$app->db;
        $tx = $db->beginTransaction();
        try {
            $persona = new Persona();
            $persona->scenario = Persona::SCENARIOCREATEUPDATE;
            $persona->nombre = self::NOMBRE;
            $persona->apellido = self::APELLIDO;
            $persona->documento = $dni;
            $persona->fecha_nacimiento = '1984-01-01';
            $persona->id_tipodoc = 1;
            $persona->id_estado_civil = 1;
            $persona->acredita_identidad = 1;
            $persona->sexo_biologico = 1;
            $persona->genero = 1;

            if (!$persona->save()) {
                $tx->rollBack();
                return [
                    'ok' => false,
                    'message' => 'Error guardando la persona de prueba.',
                    'errors' => $persona->getErrors(),
                ];
            }

            $user = new User();
            $user->username = $username;
            $user->email = 'prueba_' . $dni . '@example.com';
            $user->status = User::STATUS_ACTIVE;
            $user->setPassword(Yii::$app->security->generateRandomString(32));
            $user->generateAuthKey();

            if (!$user->save()) {
                $tx->rollBack();
                return [
                    'ok' => false,
                    'message' => 'Error creando el usuario de prueba.',
                    'errors' => $user->getErrors(),
                ];
            }

            $persona->id_user = $user->id;
            $persona->scenario = Persona::SCENARIOUSERUPDATE;
            if (!$persona->save(false)) {
                $tx->rollBack();
                return [
                    'ok' => false,
                    'message' => 'Error vinculando usuario a la persona.',
                    'errors' => $persona->getErrors(),
                ];
            }

            $tx->commit();
        } catch (\Throwable $e) {
            if ($tx->isActive) {
                $tx->rollBack();
            }
            Yii::error('CrearUsuarioDePruebaHelper: ' . $e->getMessage(), __METHOD__);
            return [
                'ok' => false,
                'message' => 'Error inesperado: ' . $e->getMessage(),
            ];
        }

        try {
            if (!Yii::$app->has('authManager', true)) {
                // Consola u otra app sin RBAC.
            } elseif (class_exists(\common\models\BioenlaceDbManager::class)
                && method_exists(\common\models\BioenlaceDbManager::class, 'asignarRolPacienteSiNoExiste')
            ) {
                \common\models\BioenlaceDbManager::asignarRolPacienteSiNoExiste($user->id);
            } else {
                $pacienteRole = Role::findOne(['name' => 'paciente']);
                if ($pacienteRole) {
                    Yii::$app->authManager->assign($pacienteRole, $user->id);
                }
            }
        } catch (\Throwable $e) {
            Yii::warning('No se pudo asignar rol paciente al usuario de prueba: ' . $e->getMessage(), 'registro');
        }

        return [
            'ok' => true,
            'message' => 'Usuario de prueba creado correctamente.',
            'persona' => [
                'id_persona' => $persona->id_persona,
                'nombre' => $persona->nombre,
                'apellido' => $persona->apellido,
                'documento' => $persona->documento,
            ],
            'user' => [
                'id' => $user->id,
                'username' => $user->username,
                'email' => $user->email,
            ],
        ];
    }
}
