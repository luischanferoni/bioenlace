<?php

namespace common\components\Domain\Person\Service;

use Yii;
use common\components\Domain\Integrations\Identity\DiditClient;
use common\components\Domain\Person\Service\PacienteDomicilioVerificacionService;
use common\components\Domain\Person\Service\PacienteContextoService;
use common\models\Person\Persona;
use common\models\User;
use common\models\rbac\AuthRole;
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
            $detalle = trim((string) ($diditResult['message'] ?? ''));
            $estado = trim((string) ($diditResult['status'] ?? 'unknown'));
            throw new \RuntimeException(
                'Verificación de identidad rechazada por Didit'
                . ($estado !== '' ? ' (estado: ' . $estado . ')' : '')
                . ($detalle !== '' ? ': ' . $detalle : '')
            );
        }

        $dni = $this->normalizeDocumento((string) $diditResult['documento']);
        $nombre = $this->normalizePersonName((string) $diditResult['nombre']);
        $apellido = $this->normalizePersonName((string) $diditResult['apellido']);

        if ($dni === '' || $nombre === '' || $apellido === '') {
            throw new \RuntimeException('La respuesta de Didit no contiene datos mínimos de identidad (dni, nombre, apellido).');
        }

        $fechaNacimiento = $this->normalizeBirthDate($diditResult['fecha_nacimiento'] ?? null)
            ?? $this->normalizeBirthDate($bodyParams['fecha_nacimiento'] ?? null);
        if ($fechaNacimiento === null || $fechaNacimiento === '') {
            throw new \RuntimeException('Didit no devolvió fecha de nacimiento; no se puede completar el registro.');
        }

        $sexoBiologico = (int) ($diditResult['sexo_biologico'] ?? 0);
        if (!in_array($sexoBiologico, [1, 2], true)) {
            throw new \RuntimeException('Didit no devolvió sexo biológico válido; no se puede completar el registro.');
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
        $persona->documento_propio = 1;
        $persona->acredita_identidad = 1;
        $persona->fecha_nacimiento = $fechaNacimiento;
        $persona->id_tipodoc = $diditResult['id_tipodoc'] ?? $persona->id_tipodoc ?? 1;
        $persona->id_estado_civil = $diditResult['id_estado_civil'] ?? $persona->id_estado_civil ?? 1;
        $genero = (int) ($diditResult['genero'] ?? 0);
        $persona->genero = in_array($genero, [1, 2], true) ? $genero : $sexoBiologico;
        $persona->sexo_biologico = $sexoBiologico;
        $persona->sexo = $sexoBiologico === 2 ? 'M' : 'F';

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
            Yii::warning('Registro Didit: validación Persona: ' . json_encode($persona->getErrors()), 'registro');
            throw new \RuntimeException(
                'Error guardando datos de la persona: ' . json_encode($persona->getErrors(), JSON_UNESCAPED_UNICODE)
            );
        }

        return $this->finalizarAltaPaciente($persona, $bodyParams, $tipo, $esNueva, $diditResult);
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
                if ($tipo === 'paciente') {
                    if (class_exists(\common\models\BioenlaceDbManager::class) && method_exists(\common\models\BioenlaceDbManager::class, 'asignarRolPacienteSiNoExiste')) {
                        \common\models\BioenlaceDbManager::asignarRolPacienteSiNoExiste($user->id);
                    }
                } elseif ($tipo === 'medico') {
                    $medicoRole = AuthRole::findOne(['name' => 'Medico']) ?? AuthRole::findOne(['name' => 'medico']);
                    if ($medicoRole) {
                        Yii::$app->authManager->assign($medicoRole, $user->id);
                    }
                }
            } catch (\Throwable $e) {
                Yii::warning('No se pudo asignar rol al usuario recién creado: ' . $e->getMessage(), 'registro');
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
                Yii::warning('Error obteniendo rol de usuario en RegistroService: ' . $e->getMessage(), 'registro');
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

    private function normalizeDocumento(string $documento): string
    {
        return preg_replace('/\D+/', '', $documento) ?? '';
    }

    private function normalizePersonName(string $name): string
    {
        $name = trim(preg_replace('/\s+/u', ' ', $name) ?? '');
        if ($name === '') {
            return '';
        }

        return trim(preg_replace('/[^A-ZÁÉÍÓÚÑa-záéíóúñ\s]/u', '', $name) ?? '');
    }

    private function normalizeBirthDate($value): ?string
    {
        if ($value === null) {
            return null;
        }
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        if (preg_match('/^(\d{4}-\d{2}-\d{2})/', $value, $matches)) {
            return $matches[1];
        }

        if (preg_match('#^(\d{2})/(\d{2})/(\d{4})$#', $value, $matches)) {
            return $matches[3] . '-' . $matches[2] . '-' . $matches[1];
        }

        if (preg_match('/^\d{6}$/', $value)) {
            $yy = (int) substr($value, 0, 2);
            $mm = substr($value, 2, 2);
            $dd = substr($value, 4, 2);
            $year = $yy > 30 ? 1900 + $yy : 2000 + $yy;

            return sprintf('%04d-%s-%s', $year, $mm, $dd);
        }

        return $value;
    }
}

