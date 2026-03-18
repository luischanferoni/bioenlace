<?php

namespace common\components;

use Yii;
use common\components\Integrations\Identity\DiditClient;
use common\models\Persona;
use common\models\PersonaMpi;
use common\models\User;
use webvimark\modules\UserManagement\models\rbacDB\Role;
use Firebase\JWT\JWT;

/**
 * Servicio de orquestación para el registro de pacientes y médicos.
 *
 * Se encarga de:
 *  - Verificar identidad con Verifik.
 *  - Crear o actualizar registros en la tabla personas.
 *  - Sincronizar información mínima con MPI (id_mpi).
 *  - Validar profesionales contra REFEPS/SISA cuando el tipo es "medico".
 */
class RegistroService
{
    /**
     * Ejecuta el flujo de registro unificado utilizando Didit como proveedor de identidad.
     *
     * @param array $bodyParams
     * @return array
     *
     * @throws \RuntimeException
     */
    public function registrar(array $bodyParams): array
    {
        $tipo = $bodyParams['tipo'] ?? null;
        $verificationId = $bodyParams['verification_id'] ?? null;

        if (!$tipo || !in_array($tipo, ['paciente', 'medico'], true)) {
            throw new \RuntimeException('El campo "tipo" es requerido y debe ser "paciente" o "medico".');
        }

        if (!$verificationId) {
            throw new \RuntimeException('El campo "verification_id" de Didit es requerido para el registro.');
        }

        /** @var DiditClient $didit */
        $didit = Yii::$container->has(DiditClient::class)
            ? Yii::$container->get(DiditClient::class)
            : new DiditClient();

        $diditResult = $didit->getIdentityVerification($verificationId);

        if ($diditResult['success'] === false || $diditResult['status'] === 'rejected') {
            throw new \RuntimeException('Verificación de identidad rechazada por Didit');
        }

        $dni = $diditResult['documento'] ?? null;
        $nombre = $diditResult['nombre'] ?? null;
        $apellido = $diditResult['apellido'] ?? null;

        if (!$dni || !$nombre || !$apellido) {
            throw new \RuntimeException('La respuesta de Didit no contiene datos mínimos de identidad (dni, nombre, apellido).');
        }

        // Crear o actualizar persona en base a los datos devueltos por Didit.
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
        $persona->acredita_identidad = 1; // Verificación de identidad realizada con Didit

        if (!empty($diditResult['fecha_nacimiento'])) {
            $persona->fecha_nacimiento = $diditResult['fecha_nacimiento'];
        } elseif (!empty($bodyParams['fecha_nacimiento'])) {
            $persona->fecha_nacimiento = $bodyParams['fecha_nacimiento'];
        }
        if (empty($persona->fecha_nacimiento)) {
            $persona->fecha_nacimiento = $bodyParams['fecha_nacimiento'] ?? null;
        }

        // Valores mapeados desde Didit (nunca null: genero 0=no especificado, id_tipodoc/id_estado_civil default 1)
        $persona->id_tipodoc = $diditResult['id_tipodoc'] ?? $persona->id_tipodoc ?? 1;
        $persona->id_estado_civil = $diditResult['id_estado_civil'] ?? $persona->id_estado_civil ?? 1;
        $persona->genero = $diditResult['genero'] ?? $persona->genero ?? 0;
        $persona->sexo_biologico = $diditResult['sexo_biologico'] ?? $persona->sexo_biologico ?? 0;

        if (property_exists($persona, 'email') && !empty($bodyParams['email'])) {
            $persona->email = $bodyParams['email'];
        }
        if (property_exists($persona, 'telefono') && !empty($bodyParams['telefono'])) {
            $persona->telefono = $bodyParams['telefono'];
        }

        if (property_exists($persona, 'didit_reference_id') && !empty($diditResult['didit_reference_id'])) {
            $persona->didit_reference_id = $diditResult['didit_reference_id'];
        }
        if (property_exists($persona, 'didit_last_kyc_verification_id')) {
            $persona->didit_last_kyc_verification_id = $diditResult['verification_id'] ?? $verificationId;
        }

        if (!$persona->save()) {
            throw new \RuntimeException('Error guardando datos de la persona: ' . json_encode($persona->getErrors()));
        }

        $mpiInfo = $this->syncMpiPersona($persona);

        $refepsInfo = null;
        if ($tipo === 'medico') {
            $refepsInfo = $this->verifyRefEpsMedico($persona);
            if ($refepsInfo['es_profesional'] !== true) {
                throw new \RuntimeException('El DNI no corresponde a un profesional de la salud registrado en REFEPS/SISA');
            }
        }

        // Crear o vincular usuario de aplicación asociado a la persona
        $user = null;
        if ($persona->id_user) {
            $user = User::findOne($persona->id_user);
        }

        if ($user === null) {
            $user = new User();
            // Username basado en tipo + documento
            $user->username = ($tipo === 'medico' ? 'medico_' : 'paciente_') . $dni;
            // Email: usar el provisto o un placeholder derivado del documento
            $email = $bodyParams['email'] ?? null;
            if (empty($email)) {
                $email = $dni . '@example.com';
            }
            $user->email = $email;
            $user->status = User::STATUS_ACTIVE;
            $user->setPassword(Yii::$app->security->generateRandomString(32));
            $user->generateAuthKey();

            if (!$user->save()) {
                throw new \RuntimeException('Error creando usuario de aplicación: ' . json_encode($user->getErrors()));
            }

            // Vincular persona al usuario recién creado (obligatorio para que queden asociados)
            $persona->id_user = $user->id;
            $persona->scenario = Persona::SCENARIOUSERUPDATE;
            if (!$persona->save(false)) {
                throw new \RuntimeException('Error actualizando id_user en persona: ' . json_encode($persona->getErrors()));
            }

            // Asignar rol según tipo
            try {
                if ($tipo === 'paciente') {
                    // Usa helper existente para pacientes si está disponible
                    if (class_exists(\common\models\SisseDbManager::class) && method_exists(\common\models\SisseDbManager::class, 'asignarRolPacienteSiNoExiste')) {
                        \common\models\SisseDbManager::asignarRolPacienteSiNoExiste($user->id);
                    }
                } elseif ($tipo === 'medico') {
                    $medicoRole = Role::findOne(['name' => 'Medico']) ?? Role::findOne(['name' => 'medico']);
                    if ($medicoRole) {
                        Yii::$app->authManager->assign($medicoRole, $user->id);
                    }
                }
            } catch (\Throwable $e) {
                Yii::warning('No se pudo asignar rol al usuario recién creado: ' . $e->getMessage(), 'registro');
            }
        }

        // Obtener rol principal para incluir en el token/respuesta
        $roleName = 'usuario';
        if ($user) {
            try {
                $roles = Role::getUserRoles($user->id);
                if (!empty($roles)) {
                    $firstRole = reset($roles);
                    if ($firstRole && isset($firstRole->name)) {
                        $roleName = $firstRole->name;
                    }
                }
            } catch (\Throwable $e) {
                Yii::warning('Error obteniendo rol de usuario en RegistroService: ' . $e->getMessage(), 'registro');
            }
        }

        // Generar JWT para permitir que las apps inicien sesión inmediatamente después del registro
        $token = null;
        if ($user) {
            $jwtSecret = Yii::$app->params['jwtSecret'] ?? null;
            if ($jwtSecret) {
                $payload = [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'role' => $roleName,
                    'id_persona' => $persona->id_persona,
                    'iat' => time(),
                    'exp' => time() + (24 * 60 * 60),
                ];
                try {
                    $token = JWT::encode($payload, $jwtSecret, 'HS256');
                } catch (\Throwable $e) {
                    Yii::error('Error generando JWT en RegistroService: ' . $e->getMessage(), 'registro');
                }
            } else {
                Yii::warning('jwtSecret no configurado en params; no se generará token en RegistroService.', 'registro');
            }
        }

        $personaData = [
            'id_persona' => $persona->id_persona,
            'nombre' => $persona->nombre,
            'apellido' => $persona->apellido,
            'documento' => $persona->documento,
            'fecha_nacimiento' => $persona->fecha_nacimiento,
            'tipo' => $tipo,
            'es_nueva' => $esNueva,
        ];

        return [
            'persona' => $personaData,
            'didit' => $diditResult,
            'mpi' => $mpiInfo,
            'refeps' => $refepsInfo,
            'user' => $user ? [
                'id' => $user->id,
                'username' => $user->username,
                'email' => $user->email,
                'role' => $roleName,
            ] : null,
            'token' => $token,
        ];
    }

    /**
     * Sincroniza información mínima de la persona con el MPI (id_mpi) si el componente está disponible.
     *
     * @param Persona $persona
     * @return array
     */
    protected function syncMpiPersona(Persona $persona): array
    {
        if (!Yii::$app->has('mpi')) {
            return [
                'empadronado' => false,
                'detalles' => [
                    'message' => 'Componente MPI no configurado en la aplicación',
                ],
            ];
        }

        try {
            /** @var \frontend\components\Mpi $mpi */
            $mpi = Yii::$app->mpi;

            $respuesta = $mpi->traerPaciente($persona->id_persona);
            $idMpi = $respuesta['data']['paciente']['set_minimo']['identificador']['mpi'] ?? null;

            $empadronado = false;

            if ($idMpi) {
                /** @var PersonaMpi|null $personaMpi */
                $personaMpi = PersonaMpi::findOne($persona->id_persona);
                if ($personaMpi) {
                    $personaMpi->id_mpi = $idMpi;
                    $personaMpi->save(false);
                    $empadronado = true;
                }
            }

            return [
                'empadronado' => $empadronado,
                'detalles' => $respuesta,
            ];
        } catch (\Throwable $e) {
            Yii::error('Error sincronizando persona con MPI: ' . $e->getMessage(), 'mpi');
            return [
                'empadronado' => false,
                'detalles' => [
                    'message' => 'Error al comunicarse con MPI',
                    'exception' => $e->getMessage(),
                ],
            ];
        }
    }

    /**
     * Verifica en REFEPS/SISA si la persona es un profesional de la salud registrado.
     *
     * @param Persona $persona
     * @return array
     */
    protected function verifyRefEpsMedico(Persona $persona): array
    {
        if (!Yii::$app->has('sisa')) {
            return [
                'es_profesional' => null,
                'detalles' => [
                    'message' => 'Componente SISA/REFEPS no configurado en la aplicación',
                ],
            ];
        }

        try {
            $dni = $persona->documento;

            $nombreCompleto = $persona->getNombreCompleto(Persona::FORMATO_NOMBRE_A_OA_N_ON);
            $apellidoCompleto = $persona->apellido;
            $nombreSolo = $persona->nombre;

            if (strpos($nombreCompleto, ',') !== false) {
                [$apellidoCompletoFromFormat, $nombreSoloFromFormat] = explode(',', $nombreCompleto, 2);
                $apellidoCompleto = trim($apellidoCompletoFromFormat);
                $nombreSolo = trim($nombreSoloFromFormat);
            }

            /** @var \frontend\components\apis\Sisa $sisa */
            $sisa = Yii::$app->sisa;

            $rawResponse = $sisa->getProfesionalesDeSantiago($apellidoCompleto, $nombreSolo, '', $dni);

            $decoded = json_decode($rawResponse, true);

            $esProfesional = false;

            if (is_array($decoded)) {
                if (isset($decoded['ok']) && $decoded['ok'] === true) {
                    $esProfesional = true;
                } elseif (isset($decoded['total']) && (int) $decoded['total'] > 0) {
                    $esProfesional = true;
                } elseif (isset($decoded['profesionales']) && is_array($decoded['profesionales']) && count($decoded['profesionales']) > 0) {
                    $esProfesional = true;
                }
            }

            return [
                'es_profesional' => $esProfesional,
                'detalles' => $decoded ?? $rawResponse,
            ];
        } catch (\Throwable $e) {
            Yii::error('Error verificando profesional en REFEPS/SISA: ' . $e->getMessage(), 'refeps');
            return [
                'es_profesional' => false,
                'detalles' => [
                    'message' => 'Error al comunicarse con REFEPS/SISA',
                    'exception' => $e->getMessage(),
                ],
            ];
        }
    }
}

