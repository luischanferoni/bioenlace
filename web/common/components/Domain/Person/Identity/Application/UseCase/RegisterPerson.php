<?php

namespace common\components\Domain\Person\Identity\Application\UseCase;

use Yii;
use common\components\Domain\Person\Identity\Application\Service\DiditIdentityService;
use common\components\Domain\Person\Identity\Application\Service\PacienteDomicilioVerificacionService;
use common\components\Domain\Person\Identity\Application\Service\PacienteContextoService;
use common\models\Person\Persona;
use common\models\Platform\User;
use common\components\Platform\Core\Permission\RbacRoleQueryService;
use common\components\Platform\Core\Auth\ApiAuthSessionService;
use Firebase\JWT\JWT;

/**
 * Servicio de orquestación para el registro de pacientes.
 *
 * Se encarga de:
 *  - Verificar identidad con Didit.
 *  - Crear o actualizar registros en la tabla personas.
 *  - Inicializar contexto paciente y verificación de domicilio MPI (tipo paciente).
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

        if ($tipo !== 'paciente') {
            throw new \RuntimeException('Este flujo solo registra pacientes.');
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
     * Cierra el alta de paciente tras persistir identidad (Didit, lector DNI staff, etc.).
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
        if ($tipo !== 'paciente') {
            throw new \InvalidArgumentException('Este flujo solo registra pacientes.');
        }

        $dni = (string) $persona->documento;

        (new PacienteDomicilioVerificacionService())->iniciarTrasRegistro($persona);
        $pacienteContexto = (new PacienteContextoService())->export(
            (new PacienteContextoService())->getOrCreate((int) $persona->id_persona)
        );

        // Crear o vincular usuario de aplicación asociado a la persona
        $user = null;
        if ($persona->id_user) {
            $user = User::findOne($persona->id_user);
        }

        if ($user === null) {
            $user = new User();
            $user->username = 'paciente_' . $dni;
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
                $expiresAt = time() + (24 * 60 * 60);
                $payload = [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'role' => $roleName,
                    'id_persona' => $persona->id_persona,
                    'iat' => time(),
                    'exp' => $expiresAt,
                    'jti' => ApiAuthSessionService::issue(
                        (int) $user->id,
                        $expiresAt,
                        'patient-registration'
                    ),
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
            'refeps' => null,
            'user' => $user ? [
                'id' => $user->id,
                'username' => $user->username,
                'email' => $user->email,
                'role' => $roleName,
            ] : null,
            'token' => $token,
        ];
    }

}
