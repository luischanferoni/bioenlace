<?php

namespace common\components\Integrations\Identity;

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
     * Obtiene y normaliza el resultado de una verificación KYC completa (documento + selfie + liveness).
     *
     * @param string $verificationId
     * @return array
     */
    public function getIdentityVerification(string $verificationId): array
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

            $data = $response->data;

            $status = $data['status'] ?? 'unknown';
            $normalizedStatus = $this->normalizeStatus($status);

            // Soporta Session API (id_verifications) y formato legacy (person + document)
            $idVerifications = $data['id_verifications'] ?? [];
            $firstIdVerification = is_array($idVerifications) && isset($idVerifications[0]) ? $idVerifications[0] : [];
            $documentTypeString = null;
            if (!empty($firstIdVerification)) {
                $persona = [
                    'first_name' => $firstIdVerification['first_name'] ?? null,
                    'last_name' => $firstIdVerification['last_name'] ?? null,
                    'date_of_birth' => $firstIdVerification['date_of_birth'] ?? null,
                    'gender' => $firstIdVerification['gender'] ?? null,
                ];
                $document = [
                    'number' => $firstIdVerification['document_number'] ?? $firstIdVerification['personal_number'] ?? null,
                    'type_id' => $firstIdVerification['document_type_id'] ?? null,
                    'type' => $firstIdVerification['document_type'] ?? null,
                ];
                $documentTypeString = $firstIdVerification['document_type'] ?? null;
                $maritalStatus = $firstIdVerification['marital_status'] ?? null;
            } else {
                $persona = $data['person'] ?? [];
                $document = $data['document'] ?? [];
                $documentTypeString = $document['type'] ?? null;
                $maritalStatus = $persona['marital_status'] ?? null;
            }

            // Género: Didit 'M'|'F'|'U'; nosotros 1=M, 2=F, 0=no especificado (genero no acepta null)
            $generoRaw = $persona['gender_id'] ?? $persona['gender'] ?? $persona['sex'] ?? null;
            if ($generoRaw === 'M' || $generoRaw === 'male') {
                $genero = 1;
            } elseif ($generoRaw === 'F' || $generoRaw === 'female') {
                $genero = 2;
            } else {
                $genero = 0; // 'U' o no informado
            }
            $sexoBiologicoRaw = $persona['biological_sex_id'] ?? $persona['sex_id'] ?? $generoRaw;
            if ($sexoBiologicoRaw === 'M' || $sexoBiologicoRaw === 'male') {
                $sexoBiologico = 1;
            } elseif ($sexoBiologicoRaw === 'F' || $sexoBiologicoRaw === 'female') {
                $sexoBiologico = 2;
            } elseif (is_numeric($sexoBiologicoRaw) && (int) $sexoBiologicoRaw >= 0) {
                $sexoBiologico = (int) $sexoBiologicoRaw;
            } else {
                $sexoBiologico = $genero; // mismo que género si no viene
            }

            $documentNumber = $document['number'] ?? null;
            $idTipodoc = $this->resolveIdTipodoc($document, $documentTypeString);
            $idEstadoCivil = $this->mapMaritalStatusToIdEstadoCivil($maritalStatus);

            return [
                'success' => $normalizedStatus === 'approved',
                'status' => $normalizedStatus,
                'verification_id' => $data['id'] ?? $data['session_id'] ?? $verificationId,
                'message' => 'Respuesta recibida desde Didit (identity verification)',
                'documento' => $documentNumber,
                'nombre' => $persona['first_name'] ?? null,
                'apellido' => $persona['last_name'] ?? null,
                'fecha_nacimiento' => $persona['date_of_birth'] ?? null,
                'genero' => $genero,
                'sexo_biologico' => $sexoBiologico,
                'id_tipodoc' => $idTipodoc,
                'id_estado_civil' => $idEstadoCivil,
                'didit_reference_id' => $data['user_reference'] ?? $data['vendor_data'] ?? null,
                'raw' => $data,
                'errors' => null,
            ];
        } catch (\Throwable $e) {
            Yii::error('Excepción llamando a Didit identity verification: ' . $e->getMessage(), 'didit');
            return $this->buildExceptionResult('identity', $e);
        }
    }

    /**
     * Obtiene y normaliza el resultado de una autenticación biométrica (selfie + liveness + face match).
     *
     * @param string $verificationId
     * @return array
     */
    public function getBiometricAuth(string $verificationId): array
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

            $data = $response->data;

            $status = $data['status'] ?? 'unknown';
            $normalizedStatus = $this->normalizeStatus($status);

            $subject = $data['subject'] ?? [];

            return [
                'success' => $normalizedStatus === 'approved',
                'status' => $normalizedStatus,
                'verification_id' => $data['id'] ?? $verificationId,
                'message' => 'Respuesta recibida desde Didit (biometric auth)',
                'linked_document' => $subject['document_number'] ?? null,
                'didit_reference_id' => $data['user_reference'] ?? null,
                'raw' => $data,
                'errors' => null,
            ];
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

        return new Client([
            'baseUrl' => $baseUrl,
            'requestConfig' => [
                'format' => Client::FORMAT_JSON,
            ],
            'responseConfig' => [
                'format' => Client::FORMAT_JSON,
            ],
        ]);
    }

    /**
     * Construye los headers de autenticación para Didit.
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
     * Ajustar IDs según la tabla tipos_documentos del proyecto.
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
     * Ajustar IDs según la tabla estado_civil del proyecto.
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

        if (in_array($lower, ['rejected', 'invalid', 'failed', 'error'], true)) {
            return 'rejected';
        }

        if (in_array($lower, ['pending', 'in_progress', 'processing'], true)) {
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

