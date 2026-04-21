<?php

namespace frontend\modules\api\v1\controllers;

use Yii;
use common\components\Services\RegistroService;
use common\models\Persona;
use common\models\User;
use webvimark\modules\UserManagement\models\rbacDB\Role;

/**
 * Controlador de registro unificado para pacientes y médicos.
 *
 * Endpoint principal:
 *
 * POST /api/v1/registro/registrar
 *
 * Payload esperado (JSON o x-www-form-urlencoded):
 *
 * {
 *   "tipo": "paciente" | "medico",
 *   "verification_id": "didit-verification-id",
 *   "fecha_nacimiento": "1984-01-01",   // opcional, solo si no viene de Didit
 *   "email": "persona@example.com",     // opcional según caso de uso
 *   "telefono": "+54...",              // opcional
 *   "extras": { ... }                  // opcional, datos adicionales
 * }
 *
 * Respuesta (éxito):
 *
 * {
 *   "success": true,
 *   "message": "Persona registrada exitosamente",
 *   "data": {
 *     "persona": {
 *       "id_persona": 123,
 *       "nombre": "Mercedes",
 *       "apellido": "Diaz",
 *       "documento": "29486884",
 *       "fecha_nacimiento": "1984-01-01",
 *       "tipo": "paciente"
 *     },
 *     "didit": {
 *       "verification_id": "...",
 *       "status": "approved" | "rejected" | "pending"
 *     },
 *     "mpi": {
 *       "empadronado": true,
 *       "detalles": { ... }
 *     },
 *     "refeps": {
 *       "es_profesional": true,
 *       "detalles": { ... }
 *     }
 *   }
 * }
 *
 * Respuesta (error):
 *
 * {
 *   "success": false,
 *   "message": "Motivo del error",
 *   "errors": { ... } // detalles opcionales
 * }
 */
class RegistroController extends BaseController
{
    /**
     * Este controlador trabaja conceptualmente sobre el modelo Persona.
     *
     * @var string
     */
    /**
     * Acciones sin autenticación: permitimos que el registro se haga sin token.
     *
     * @var string[]
     */
    public static $authenticatorExcept = ['registrar', 'simular-paciente-mercedes'];

    /**
     * Deshabilitamos las acciones REST por defecto; usamos acciones personalizadas.
     *
     * @return array
     */
    public function actions()
    {
        $actions = parent::actions();
        unset($actions['index'], $actions['view'], $actions['create'], $actions['update'], $actions['delete']);
        return $actions;
    }

    /**
     * Definición de verbs para el endpoint.
     *
     * @return array
     */
    protected function verbs()
    {
        $verbs = parent::verbs();
        $verbs['registrar'] = ['POST', 'OPTIONS'];
        $verbs['simular-paciente-mercedes'] = ['POST', 'OPTIONS'];
        return $verbs;
    }

    /**
     * Endpoint unificado de registro de pacientes y médicos utilizando Didit.
     *
     * Ruta: POST /api/v1/registro/registrar
     *
     * - Valida parámetros mínimos del request.
     * - Delegará la lógica de orquestación a un servicio de registro
     *   (por ejemplo, common\components\RegistroService), que se encargará de:
     *      * llamar a Verifik,
     *      * crear/actualizar Persona en la base,
     *      * sincronizar con MPI,
     *      * y, en caso de médicos, validar contra REFEPS.
     *
     * Por ahora solo valida y estructura el contrato de entrada/salida;
     * la integración concreta se añadirá en pasos posteriores.
     *
     * @return array
     */
    public function actionRegistrar()
    {
        $request = Yii::$app->request;
        $bodyParams = $request->getBodyParams();

        $tipo = $bodyParams['tipo'] ?? null;
        $verificationId = $bodyParams['verification_id'] ?? null;

        if (!$tipo || !in_array($tipo, ['paciente', 'medico'], true)) {
            return $this->error('El campo "tipo" es requerido y debe ser "paciente" o "medico".', null, 400);
        }

        if (!$verificationId) {
            return $this->error('El campo "verification_id" (Didit) es requerido.', null, 400);
        }

        /** @var RegistroService $service */
        $service = Yii::$container->has(RegistroService::class)
            ? Yii::$container->get(RegistroService::class)
            : new RegistroService();

        try {
            $result = $service->registrar($bodyParams);
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), null, 422);
        } catch (\Throwable $e) {
            Yii::error('Error inesperado en RegistroService: ' . $e->getMessage(), 'registro');
            return $this->error('Error interno al procesar el registro', null, 500);
        }

        return $this->success(
            $result,
            'Solicitud de registro recibida correctamente',
            202
        );
    }

    /**
     * Endpoint de prueba: crear/vincular un paciente simulado (Mercedes Diaz, DNI 29558371)
     * sin llamar a Didit ni a servicios externos.
     *
     * Ruta: POST /api/v1/registro/simular-paciente-mercedes
     */
    public function actionSimularPacienteMercedes()
    {
        $dni = '29486884';
        $nombre = 'Luis';
        $apellido = 'Chanferoni';

        // Buscar o crear Persona
        $persona = Persona::findOne(['documento' => $dni]);
        $esNueva = false;

        if ($persona === null) {
            $persona = new Persona();
            $esNueva = true;
        }

        $persona->scenario = Persona::SCENARIOCREATEUPDATE;
        $persona->nombre = $nombre;
        $persona->apellido = $apellido;
        $persona->documento = $dni;
        $persona->fecha_nacimiento = $persona->fecha_nacimiento ?: '1982-07-14';
        $persona->id_tipodoc = $persona->id_tipodoc ?: 1;
        $persona->id_estado_civil = $persona->id_estado_civil ?: 1;
        $persona->acredita_identidad = 1; // Simula registro con identidad acreditada (Didit)
        if ($persona->sexo_biologico === null && $persona->genero === null) {
            $persona->sexo_biologico = 2;
            $persona->genero = 2;
        }

        if (!$persona->save()) {
            return $this->error(
                'Error guardando datos de la persona simulada: ' . json_encode($persona->getErrors()),
                null,
                422
            );
        }

        // Crear o reutilizar usuario asociado
        $user = null;
        if ($persona->id_user) {
            $user = User::findOne($persona->id_user);
        }

        if ($user === null) {
            $user = new User();
            $user->username = 'paciente_' . $dni;
            $user->email = $dni . '@example.com';
            $user->status = User::STATUS_ACTIVE;
            $user->setPassword(Yii::$app->security->generateRandomString(32));
            $user->generateAuthKey();

            if (!$user->save()) {
                return $this->error(
                    'Error creando usuario para la persona simulada: ' . json_encode($user->getErrors()),
                    null,
                    422
                );
            }

            $persona->id_user = $user->id;
            $persona->scenario = Persona::SCENARIOUSERUPDATE;
            $persona->save(false);

            // Asignar rol paciente si existe
            try {
                if (class_exists(\common\models\BioenlaceDbManager::class)
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
                Yii::warning('No se pudo asignar rol paciente al usuario simulado: ' . $e->getMessage(), 'registro');
            }
        }

        $personaData = [
            'id_persona' => $persona->id_persona,
            'nombre' => $persona->nombre,
            'apellido' => $persona->apellido,
            'documento' => $persona->documento,
            'es_nueva' => $esNueva,
        ];

        $userData = $user ? [
            'id' => $user->id,
            'username' => $user->username,
            'email' => $user->email,
        ] : null;

        return $this->success(
            [
                'persona' => $personaData,
                'user' => $userData,
            ],
            'Paciente simulado (Mercedes Diaz) creado/actualizado correctamente'
        );
    }
}

