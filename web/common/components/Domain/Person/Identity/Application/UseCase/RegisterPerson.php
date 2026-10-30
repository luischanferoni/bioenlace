<?php

namespace common\components\Domain\Person\Identity\Application\UseCase;

use Yii;
use common\components\Domain\Person\Identity\Application\Service\DiditIdentityService;
use common\components\Domain\Person\Identity\Application\Service\PacienteDomicilioVerificacionService;
use common\components\Domain\Person\Identity\Application\Service\PacienteContextoService;
use common\models\Person\Persona;
use common\models\Platform\User;
use common\models\Platform\Permission\AuthRole;
use common\components\Platform\Core\Permission\RbacRoleQueryService;
use Firebase\JWT\JWT;

/**
 * Servicio de orquestación para el registro de pacientes y médicos.
 *
 * Se encarga de:
 *  - Verificar identidad con Didit.
 *  - Crear o actualizar registros en la tabla personas.
 *  - Inicializar contexto paciente y verificación de domicilio MPI (tipo paciente).
 *  - Validar profesionales contra REFEPS/SISA cuando el tipo es "medico".
 */
class RegisterPerson
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

        $resolved = (new DiditIdentityService())->resolveAndUpsertPersona(
            $verificationId,
            $bodyParams['fecha_nacimiento'] ?? null,
            [
                'email' => $bodyParams['email'] ?? null,
                'telefono' => $bodyParams['telefono'] ?? null,
            ]
        );

        return $this->finalizarAltaPaciente(
            $resolved['persona'],
            $bodyParams,
            $tipo,
            $resolved['es_nueva'],
            $resolved['didit']
        );
    }

    /**
     * Cierra el alta de paciente/médico tras persistir identidad (Didit, lector DNI staff, etc.).
     *
     * @param array<string, mixed>|null $diditResult
     * @return array<string, mixed>
     */
    public function finalizarAltaPaciente(
        Persona $persona,
        array $bodyParams,
        string $tipo,
        bool $esNueva,
        ?array $diditResult = null,
        bool $emitJwt = true
    ): array {
        $dni = (string) $persona->documento;

        $pacienteContexto = null;
        if ($tipo === 'paciente') {
            (new PacienteDomicilioVerificacionService())->iniciarTrasRegistro($persona);
            $pacienteContexto = (new PacienteContextoService())->export(
                (new PacienteContextoService())->getOrCreate((int) $persona->id_persona)
            );
        }

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
            $user->username = ($tipo === 'medico' ? 'medico_' : 'paciente_') . $dni;
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

            $persona->id_user = $user->id;
            $persona->scenario = Persona::SCENARIOUSERUPDATE;
            if (!$persona->save(false)) {
                throw new \RuntimeException('Error actualizando id_user en persona: ' . json_encode($persona->getErrors()));
            }

            try {
                if ($tipo === 'medico') {
                    $medicoRole = AuthRole::findOne(['name' => 'Medico']) ?? AuthRole::findOne(['name' => 'medico']);
                    if ($medicoRole) {
                        Yii::$app->authManager->assign($medicoRole, $user->id);
                    }
                }
            } catch (\Throwable $e) {
                Yii::warning('No se pudo asignar rol al usuario recién creado: ' . $e->getMessage(), 'registro');
            }
        }

        if ($user !== null && $tipo === 'paciente') {
            try {
                if (class_exists(\common\models\Platform\BioenlaceDbManager::class)
                    && method_exists(\common\models\Platform\BioenlaceDbManager::class, 'asignarRolPacienteSiNoExiste')) {
                    \common\models\Platform\BioenlaceDbManager::asignarRolPacienteSiNoExiste((int) $user->id);
                }
            } catch (\Throwable $e) {
                Yii::warning('No se pudo asegurar rol paciente: ' . $e->getMessage(), 'registro');
            }
        }

        $roleName = 'usuario';
        if ($user) {
            try {
                $roles = RbacRoleQueryService::getUserRoles((int) $user->id);
                if (!empty($roles)) {
                    $firstRole = reset($roles);
                    if ($firstRole && isset($firstRole->name)) {
                        $roleName = $firstRole->name;
                    }
                }
            } catch (\Throwable $e) {
                Yii::warning('Error obteniendo rol de usuario en RegisterPerson: ' . $e->getMessage(), 'registro');
            }
        }

        $token = null;
        if ($emitJwt && $user) {
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
                    Yii::error('Error generando JWT en RegisterPerson: ' . $e->getMessage(), 'registro');
                }
            } else {
                Yii::warning('jwtSecret no configurado en params; no se generará token en RegisterPerson.', 'registro');
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
            'paciente_contexto' => $pacienteContexto,
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

