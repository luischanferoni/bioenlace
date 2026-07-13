<?php

namespace common\components\Domain\Integrations\Identity;

use Yii;
use yii\base\Component;
use yii\httpclient\Client;

/**
 * Cliente para la API de Didit (identity verification + biometric auth).
 *
 * La configuración se toma de:
 * - didit_base_url
 * - didit_api_key
 * - didit_timeout
 *
 * en Yii::$app->params.
 */
class DiditClient extends Component
{
    /**
     * Crea sesión Didit v3 (hosted URL) para verificación con foto de DNI.
     *
     * @param array<string, mixed> $options workflow_id, callback, vendor_data, language
     * @return array{success: bool, session_id?: string, url?: string, message?: string}
     */
    public function createVerificationSession(array $options = []): array
    {
        $workflowId = trim((string) ($options['workflow_id'] ?? Yii::$app->params['didit_paciente_kyc_workflow_id'] ?? ''));
        if ($workflowId === '') {
            return [
                'success' => false,
                'message' => 'didit_paciente_kyc_workflow_id no configurado.',
            ];
        }

        $baseUrl = rtrim((string) (Yii::$app->params['didit_verification_base_url'] ?? 'https://verification.didit.me'), '/');
        $apiKey = Yii::$app->params['didit_api_key'] ?? null;
        if (empty($apiKey)) {
            return [
                'success' => false,
                'message' => 'didit_api_key no configurada.',
            ];
        }

        $payload = [
            'workflow_id' => $workflowId,
            'vendor_data' => (string) ($options['vendor_data'] ?? 'staff-registro-paciente'),
            'language' => (string) ($options['language'] ?? 'es'),
        ];
        $callback = trim((string) ($options['callback'] ?? ''));
        if ($callback !== '') {
            $payload['callback'] = $callback;
            $payload['callback_method'] = (string) ($options['callback_method'] ?? 'both');
        }

        try {
            $client = new Client([
                'baseUrl' => $baseUrl,
                'requestConfig' => [
                    'format' => Client::FORMAT_JSON,
                    'options' => ['timeout' => (int) (Yii::$app->params['didit_timeout'] ?? 30)],
                ],
                'responseConfig' => ['format' => Client::FORMAT_JSON],
            ]);

            $response = $client->createRequest()
                ->setMethod('POST')
                ->setUrl('/v3/session/')
                ->addHeaders([
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                    'x-api-key' => $apiKey,
                ])
                ->setData($payload)
                ->send();

            if (!$response->isOk) {
                Yii::warning('Didit create session HTTP ' . $response->getStatusCode() . ': ' . $response->content, 'didit');

                return [
                    'success' => false,
                    'message' => 'No se pudo crear la sesión Didit.',
                    'raw' => $response->data,
                ];
            }

            $data = is_array($response->data) ? $response->data : [];

            return [
                'success' => true,
                'session_id' => (string) ($data['session_id'] ?? ''),
                'url' => (string) ($data['url'] ?? ''),
                'status' => (string) ($data['status'] ?? ''),
                'raw' => $data,
            ];
        } catch (\Throwable $e) {
            Yii::error('Didit create session: ' . $e->getMessage(), 'didit');

            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Obtiene y normaliza el resultado de una verificación KYC completa (documento + selfie + liveness).
     *
     * El SDK móvil y las sesiones hosted devuelven un session_id v3; se consulta primero
     * GET /v3/session/{id}/decision/ y, si no existe, el endpoint legacy de identity.
     *
     * @param string $verificationId session_id (v3) o verification id legacy
     * @return array
     */
    public function getIdentityVerification(string $verificationId): array
    {
        $sessionDecision = $this->fetchSessionDecision($verificationId);
        if ($sessionDecision !== null) {
            return $this->buildIdentityResultFromPayload(
                $sessionDecision,
                $verificationId,
                'Respuesta recibida desde Didit (session v3)'
            );
        }

        return $this->getLegacyIdentityVerification($verificationId);
    }

    /**
     * Obtiene y normaliza el resultado de una autenticación biométrica (selfie + liveness + face match).
     *
     * @param string $verificationId session_id (v3) o verification id legacy
     * @return array
     */
    public function getBiometricAuth(string $verificationId): array
    {
        $sessionDecision = $this->fetchSessionDecision($verificationId);
        if ($sessionDecision !== null) {
            return $this->buildBiometricResultFromPayload(
                $sessionDecision,
                $verificationId,
                'Respuesta recibida desde Didit (session v3 biometric)'
            );
        }

        return $this->getLegacyBiometricAuth($verificationId);
    }

    /**
     * GET /v3/session/{sessionId}/decision/ — decisión canónica de sesiones v3.
     *
     * @return array<string, mixed>|null null si la sesión no existe (404) en v3
     */
    protected function fetchSessionDecision(string $sessionId): ?array
    {
        $sessionId = trim($sessionId);
        if ($sessionId === '') {
            return null;
        }

        try {
            $client = $this->createVerificationHttpClient();
            $response = $client->createRequest()
                ->setMethod('GET')
                ->setUrl('/v3/session/' . rawurlencode($sessionId) . '/decision/')
                ->addHeaders($this->buildVerificationApiHeaders())
                ->send();

            if ((int) $response->getStatusCode() === 404) {
                return null;
            }

            if (!$response->isOk) {
                Yii::warning(
                    'Error HTTP en Didit session decision: ' . $response->getStatusCode() . ' ' . $response->content,
                    'didit'
                );

                return null;
            }

            return is_array($response->data) ? $response->data : null;
        } catch (\Throwable $e) {
            Yii::error('Excepción llamando a Didit session decision: ' . $e->getMessage(), 'didit');

            return null;
        }
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    protected function buildIdentityResultFromPayload(array $data, string $verificationId, string $message): array
    {
        $status = $data['status'] ?? 'unknown';
        $normalizedStatus = $this->normalizeStatus((string) $status);

        $firstIdVerification = $this->pickPreferredIdVerification($data['id_verifications'] ?? []);
        $documentTypeString = null;
        if (!empty($firstIdVerification)) {
            $identity = $this->extractIdVerificationIdentity($firstIdVerification);
            $persona = [
                'first_name' => $identity['nombre'],
                'last_name' => $identity['apellido'],
                'date_of_birth' => $identity['fecha_nacimiento'],
                'gender' => $identity['gender_raw'],
            ];
            $document = [
                'number' => $identity['documento'],
                'type_id' => $firstIdVerification['document_type_id'] ?? null,
                'type' => $firstIdVerification['document_type'] ?? null,
            ];
            $documentTypeString = $firstIdVerification['document_type'] ?? null;
            $maritalStatus = $firstIdVerification['marital_status'] ?? null;
            $sexMapped = $identity['sexo_biologico'];
        } else {
            $persona = $data['person'] ?? [];
            $document = $data['document'] ?? [];
            $documentTypeString = $document['type'] ?? null;
            $maritalStatus = $persona['marital_status'] ?? null;
            $sexMapped = null;
        }

        $generoRaw = $persona['gender_id'] ?? $persona['gender'] ?? $persona['sex'] ?? null;
        $sexFromGender = $this->mapDiditSexToBioenlace($generoRaw !== null ? (string) $generoRaw : null);
        if ($sexMapped === null) {
            $sexMapped = $sexFromGender['sexo_biologico'];
        }
        $genero = $sexFromGender['genero'] !== 0
            ? $sexFromGender['genero']
            : ($sexMapped !== 0 ? $sexMapped : 0);
        $sexoBiologico = $sexMapped !== 0 ? $sexMapped : $sexFromGender['sexo_biologico'];
        $documentNumber = $this->normalizeDocumentNumber($document['number'] ?? null);
        $idTipodoc = $this->resolveIdTipodoc($document, $documentTypeString);
        $idEstadoCivil = $this->mapMaritalStatusToIdEstadoCivil($maritalStatus);

        return [
            'success' => $normalizedStatus === 'approved',
            'status' => $normalizedStatus,
            'verification_id' => $data['session_id'] ?? $data['id'] ?? $verificationId,
            'message' => $message,
            'documento' => $documentNumber,
            'nombre' => $persona['first_name'] ?? null,
            'apellido' => $persona['last_name'] ?? null,
            'fecha_nacimiento' => $this->normalizeBirthDate($persona['date_of_birth'] ?? null),
            'genero' => $genero,
            'sexo_biologico' => $sexoBiologico,
            'id_tipodoc' => $idTipodoc,
            'id_estado_civil' => $idEstadoCivil,
            'didit_reference_id' => $data['user_reference'] ?? $data['vendor_data'] ?? null,
            'raw' => $data,
            'errors' => null,
        ];
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    protected function buildBiometricResultFromPayload(array $data, string $verificationId, string $message): array
    {
        $normalizedStatus = $this->normalizeStatus((string) ($data['status'] ?? 'unknown'));
        $firstIdVerification = $this->pickPreferredIdVerification($data['id_verifications'] ?? []);
        $linkedDocument = null;
        if (!empty($firstIdVerification)) {
            $linkedDocument = $firstIdVerification['document_number']
                ?? $firstIdVerification['personal_number']
                ?? null;
        }
        if ($linkedDocument === null && !empty($data['subject']['document_number'])) {
            $linkedDocument = $data['subject']['document_number'];
        }

        return [
            'success' => $normalizedStatus === 'approved',
            'status' => $normalizedStatus,
            'verification_id' => $data['session_id'] ?? $data['id'] ?? $verificationId,
            'message' => $message,
            'linked_document' => $linkedDocument,
            'didit_reference_id' => $data['user_reference'] ?? $data['vendor_data'] ?? null,
            'raw' => $data,
            'errors' => null,
        ];
    }

    /**
     * @param mixed $idVerifications
     * @return array<string, mixed>
     */
    protected function pickPreferredIdVerification($idVerifications): array
    {
        if (!is_array($idVerifications) || $idVerifications === []) {
            return [];
        }

        foreach ($idVerifications as $item) {
            if (!is_array($item)) {
                continue;
            }
            if ($this->normalizeStatus((string) ($item['status'] ?? '')) === 'approved') {
                return $item;
            }
        }

        return is_array($idVerifications[0]) ? $idVerifications[0] : [];
    }

    /**
     * Campos de identidad desde un ítem de id_verifications (API v3).
     *
     * @param array<string, mixed> $row
     * @return array{nombre: ?string, apellido: ?string, documento: ?string, fecha_nacimiento: ?string, gender_raw: ?string, sexo_biologico: int}
     */
    protected function extractIdVerificationIdentity(array $row): array
    {
        $nombre = trim((string) ($row['first_name'] ?? $row['first_name_latin'] ?? ''));
        $apellido = trim((string) ($row['last_name'] ?? $row['last_name_latin'] ?? ''));
        if ($nombre === '' && $apellido === '' && !empty($row['full_name'])) {
            $parts = preg_split('/\s+/u', trim((string) $row['full_name']), 2) ?: [];
            $nombre = trim((string) ($parts[0] ?? ''));
            $apellido = trim((string) ($parts[1] ?? ''));
        }

        $genderRaw = $row['gender'] ?? $row['sex'] ?? null;
        $sex = $this->mapDiditSexToBioenlace($genderRaw !== null ? (string) $genderRaw : null);

        $documento = $this->normalizeDocumentNumber(
            $row['document_number'] ?? $row['personal_number'] ?? null
        );
        $fecha = $this->normalizeBirthDate($row['date_of_birth'] ?? $row['birth_date'] ?? null);

        return [
            'nombre' => $nombre !== '' ? $nombre : null,
            'apellido' => $apellido !== '' ? $apellido : null,
            'documento' => $documento,
            'fecha_nacimiento' => $fecha,
            'gender_raw' => $genderRaw !== null ? (string) $genderRaw : null,
            'sexo_biologico' => $sex['sexo_biologico'],
        ];
    }

    /**
     * Convención Bioenlace: 1 = F, 2 = M (ver Persona::getSexoLetra).
     *
     * @return array{genero: int, sexo_biologico: int}
     */
    protected function mapDiditSexToBioenlace(?string $raw): array
    {
        if ($raw === null || trim($raw) === '') {
            return ['genero' => 0, 'sexo_biologico' => 0];
        }

        $normalized = strtoupper(trim($raw));
        if ($normalized === 'M' || $normalized === 'MALE' || $normalized === 'MASCULINO') {
            return ['genero' => 2, 'sexo_biologico' => 2];
        }
        if ($normalized === 'F' || $normalized === 'FEMALE' || $normalized === 'FEMENINO') {
            return ['genero' => 1, 'sexo_biologico' => 1];
        }

        return ['genero' => 0, 'sexo_biologico' => 0];
    }

    protected function normalizeDocumentNumber($value): ?string
    {
        if ($value === null) {
            return null;
        }
        $digits = preg_replace('/\D+/', '', (string) $value) ?? '';
        return $digits !== '' ? $digits : null;
    }

    protected function normalizeBirthDate($value): ?string
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

    /**
     * @return array<string, mixed>
     */
    protected function getLegacyIdentityVerification(string $verificationId): array
    {
        $client = $this->createHttpClient();

        try {
            $response = $client->createRequest()
                ->setMethod('GET')
                ->setUrl('/identity/verifications/' . urlencode($verificationId))
                ->addHeaders($this->buildAuthHeaders())
                ->send();

            if (!$response->isOk) {
                Yii::warning(
                    'Error HTTP en Didit identity verification: ' . $response->getStatusCode() . ' ' . $response->content,
                    'didit'
                );
                return $this->buildErrorResult('identity', 'Error HTTP al consultar Didit', $response->data, $response->getStatusCode());
            }

            $data = is_array($response->data) ? $response->data : [];

            return $this->buildIdentityResultFromPayload(
                $data,
                $verificationId,
                'Respuesta recibida desde Didit (identity verification legacy)'
            );
        } catch (\Throwable $e) {
            Yii::error('Excepción llamando a Didit identity verification: ' . $e->getMessage(), 'didit');
            return $this->buildExceptionResult('identity', $e);
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function getLegacyBiometricAuth(string $verificationId): array
    {
        $client = $this->createHttpClient();

        try {
            $response = $client->createRequest()
                ->setMethod('GET')
                ->setUrl('/biometric/verifications/' . urlencode($verificationId))
                ->addHeaders($this->buildAuthHeaders())
                ->send();

            if (!$response->isOk) {
                Yii::warning(
                    'Error HTTP en Didit biometric auth: ' . $response->getStatusCode() . ' ' . $response->content,
                    'didit'
                );
                return $this->buildErrorResult('biometric', 'Error HTTP al consultar Didit (biometric auth)', $response->data, $response->getStatusCode());
            }

            $data = is_array($response->data) ? $response->data : [];

            return $this->buildBiometricResultFromPayload(
                $data,
                $verificationId,
                'Respuesta recibida desde Didit (biometric auth legacy)'
            );
        } catch (\Throwable $e) {
            Yii::error('Excepción llamando a Didit biometric auth: ' . $e->getMessage(), 'didit');
            return $this->buildExceptionResult('biometric', $e);
        }
    }

    /**
     * Crea el cliente HTTP configurado.
     *
     * @return Client
     */
    protected function createHttpClient(): Client
    {
        $baseUrl = rtrim(Yii::$app->params['didit_base_url'] ?? 'https://api.didit.me', '/');

        return $this->createJsonHttpClient($baseUrl);
    }

    protected function createVerificationHttpClient(): Client
    {
        $baseUrl = rtrim(
            (string) (Yii::$app->params['didit_verification_base_url'] ?? 'https://verification.didit.me'),
            '/'
        );

        return $this->createJsonHttpClient($baseUrl);
    }

    protected function createJsonHttpClient(string $baseUrl): Client
    {
        $timeout = (int) (Yii::$app->params['didit_timeout'] ?? 30);

        return new Client([
            'baseUrl' => $baseUrl,
            'requestConfig' => [
                'format' => Client::FORMAT_JSON,
                'options' => [
                    'timeout' => $timeout,
                ],
            ],
            'responseConfig' => [
                'format' => Client::FORMAT_JSON,
            ],
        ]);
    }

    /**
     * Headers para verification.didit.me (sesiones v3).
     *
     * @return array<string, string>
     */
    protected function buildVerificationApiHeaders(): array
    {
        $apiKey = Yii::$app->params['didit_api_key'] ?? null;
        $headers = [
            'Accept' => 'application/json',
        ];

        if (!empty($apiKey)) {
            $headers['x-api-key'] = (string) $apiKey;
        } else {
            Yii::warning('didit_api_key no configurada; se llamará a Didit sin x-api-key.', 'didit');
        }

        return $headers;
    }

    /**
     * Construye los headers de autenticación para Didit API legacy (api.didit.me).
     *
     * @return array
     */
    protected function buildAuthHeaders(): array
    {
        $apiKey = Yii::$app->params['didit_api_key'] ?? null;
        $headers = [
            'Accept' => 'application/json',
        ];

        if (!empty($apiKey)) {
            $headers['Authorization'] = 'Bearer ' . $apiKey;
        } else {
            Yii::warning('didit_api_key no configurada; se llamará a Didit sin Authorization header.', 'didit');
        }

        return $headers;
    }

    /**
     * Resuelve id_tipodoc desde la respuesta de Didit (type_id numérico o document_type string).
     * Nunca devuelve null: id_tipodoc no acepta null en Persona.
     *
     * @param array $document con 'type_id' y/o se usa $documentTypeString
     * @param string|null $documentTypeString Didit: "Identity Card", "Passport", "Driver's License", "Residence Permit"
     * @return int
     */
    protected function resolveIdTipodoc(array $document, ?string $documentTypeString): int
    {
        if (isset($document['type_id']) && is_numeric($document['type_id']) && (int) $document['type_id'] > 0) {
            return (int) $document['type_id'];
        }
        return $this->mapDocumentTypeToIdTipodoc($documentTypeString);
    }

    /**
     * Mapeo Didit document_type (string) → id_tipodoc del nomenclador.
     * Ajustar IDs según la tabla cat_tipos_documentos del proyecto.
     *
     * @param string|null $documentType
     * @return int
     */
    protected function mapDocumentTypeToIdTipodoc(?string $documentType): int
    {
        if ($documentType === null || $documentType === '') {
            return 1;
        }
        $map = [
            'Identity Card' => 1,
            'ID' => 1,
            'Passport' => 2,
            "Driver's License" => 1,
            'Residence Permit' => 1,
        ];
        $normalized = trim($documentType);
        return $map[$normalized] ?? 1;
    }

    /**
     * Mapeo Didit marital_status (string) → id_estado_civil del nomenclador.
     * Nunca devuelve null: id_estado_civil no acepta null en Persona.
     * Ajustar IDs según la tabla cat_estado_civil del proyecto.
     *
     * @param string|null $maritalStatus Didit: SINGLE, MARRIED, DIVORCED, WIDOWED, UNKNOWN (o "Single" etc.)
     * @return int
     */
    protected function mapMaritalStatusToIdEstadoCivil(?string $maritalStatus): int
    {
        if ($maritalStatus === null || $maritalStatus === '') {
            return 1;
        }
        $map = [
            'SINGLE' => 1,
            'Single' => 1,
            'MARRIED' => 2,
            'Married' => 2,
            'DIVORCED' => 3,
            'Divorced' => 3,
            'WIDOWED' => 4,
            'Widowed' => 4,
            'UNKNOWN' => 1,
            'Unknown' => 1,
        ];
        $normalized = trim($maritalStatus);
        return $map[$normalized] ?? 1;
    }

    /**
     * Normaliza estados devueltos por Didit.
     *
     * @param string $status
     * @return string approved|rejected|pending|unknown
     */
    protected function normalizeStatus(string $status): string
    {
        $lower = strtolower($status);

        if (in_array($lower, ['approved', 'valid', 'ok', 'success'], true)) {
            return 'approved';
        }

        if (in_array($lower, ['rejected', 'declined', 'invalid', 'failed', 'error'], true)) {
            return 'rejected';
        }

        if (in_array($lower, ['pending', 'in_progress', 'processing', 'in review', 'awaiting user', 'not started'], true)) {
            return 'pending';
        }

        return 'unknown';
    }

    /**
     * Helper para resultados de error HTTP.
     *
     * @param string $context
     * @param string $message
     * @param mixed $raw
     * @param int|null $httpStatus
     * @return array
     */
    protected function buildErrorResult(string $context, string $message, $raw, ?int $httpStatus): array
    {
        return [
            'success' => false,
            'status' => 'unknown',
            'verification_id' => null,
            'message' => $message,
            'raw' => $raw,
            'errors' => [
                'context' => $context,
                'http_status' => $httpStatus,
            ],
        ];
    }

    /**
     * Helper para resultados de excepción.
     *
     * @param string $context
     * @param \Throwable $e
     * @return array
     */
    protected function buildExceptionResult(string $context, \Throwable $e): array
    {
        return [
            'success' => false,
            'status' => 'unknown',
            'verification_id' => null,
            'message' => 'Excepción al llamar a Didit (' . $context . '): ' . $e->getMessage(),
            'raw' => null,
            'errors' => [
                'context' => $context,
                'exception' => $e->getMessage(),
            ],
        ];
    }
}

