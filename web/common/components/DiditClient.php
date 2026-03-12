<?php

namespace common\components;

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

            $persona = $data['person'] ?? [];
            $document = $data['document'] ?? [];

            return [
                'success' => $normalizedStatus === 'approved',
                'status' => $normalizedStatus,
                'verification_id' => $data['id'] ?? $verificationId,
                'message' => 'Respuesta recibida desde Didit (identity verification)',
                'documento' => $document['number'] ?? null,
                'nombre' => $persona['first_name'] ?? null,
                'apellido' => $persona['last_name'] ?? null,
                'fecha_nacimiento' => $persona['date_of_birth'] ?? null,
                'didit_reference_id' => $data['user_reference'] ?? null,
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

