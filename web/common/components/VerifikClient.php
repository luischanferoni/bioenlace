<?php

namespace common\components;

use Yii;
use yii\base\Component;
use yii\httpclient\Client;

/**
 * Cliente simple para la API de Verifik.
 *
 * Este cliente está pensado principalmente para verificar DNI argentino.
 * La configuración (base URL, API key, timeouts) se lee desde Yii::$app->params:
 *
 * - verifik_base_url  (opcional, por defecto https://api.verifik.co)
 * - verifik_api_key   (obligatoria para producción)
 * - verifik_timeout   (opcional, en segundos; por defecto 10)
 *
 * NOTA IMPORTANTE:
 * ----------------
 * La estructura exacta del request/response de Verifik puede cambiar según el plan
 * contratado y la versión de su API. Este cliente implementa un wrapper genérico
 * que habrá que ajustar cuando se tenga la documentación/contrato definitivo.
 */
class VerifikClient extends Component
{
    /**
     * Verifica un DNI argentino contra Verifik.
     *
     * @param string      $dni        Número de documento (sin puntos)
     * @param string|null $nombre     Nombre declarado (opcional, puede usarse para controles adicionales)
     * @param string|null $apellido   Apellido declarado (opcional)
     * @param array       $extraData  Datos adicionales que se deseen enviar al proveedor
     *
     * @return array Estructura normalizada:
     *               [
     *                 'success' => bool,
     *                 'status' => 'aprobado' | 'rechazado' | 'desconocido',
     *                 'verification_id' => string|null,
     *                 'message' => string,
     *                 'raw' => mixed,            // respuesta cruda de Verifik (array)
     *                 'errors' => array|null,    // detalles en caso de error
     *               ]
     */
    public function verifyDni(string $dni, ?string $nombre = null, ?string $apellido = null, array $extraData = []): array
    {
        $baseUrl = rtrim(Yii::$app->params['verifik_base_url'] ?? 'https://api.verifik.co', '/');
        $apiKey = Yii::$app->params['verifik_api_key'] ?? null;
        $timeout = (int) (Yii::$app->params['verifik_timeout'] ?? 10);

        if (empty($apiKey)) {
            Yii::warning('verifik_api_key no configurada; no se puede llamar a Verifik.', 'verifik');
            return [
                'success' => false,
                'status' => 'desconocido',
                'verification_id' => null,
                'message' => 'API key de Verifik no configurada',
                'raw' => null,
                'errors' => ['api_key' => 'Falta verifik_api_key en params'],
            ];
        }

        $client = new Client([
            'baseUrl' => $baseUrl,
            'requestConfig' => [
                'format' => Client::FORMAT_JSON,
            ],
            'responseConfig' => [
                'format' => Client::FORMAT_JSON,
            ],
        ]);

        // Payload genérico; debe ajustarse según contrato específico de Verifik.
        $payload = array_merge(
            [
                'document_number' => $dni,
                'first_name' => $nombre,
                'last_name' => $apellido,
            ],
            $extraData
        );

        try {
            $response = $client->createRequest()
                ->setMethod('POST')
                ->setUrl('/v2/ar/cedula')
                ->addHeaders([
                    'Authorization' => 'Bearer ' . $apiKey,
                    'Accept' => 'application/json',
                ])
                ->setData($payload)
                ->setOptions(['timeout' => $timeout])
                ->send();

            if (!$response->isOk) {
                Yii::warning(
                    'Error HTTP en Verifik: ' . $response->getStatusCode() . ' ' . $response->content,
                    'verifik'
                );
                return [
                    'success' => false,
                    'status' => 'desconocido',
                    'verification_id' => null,
                    'message' => 'Error al comunicarse con Verifik (HTTP ' . $response->getStatusCode() . ')',
                    'raw' => $response->data,
                    'errors' => ['http_status' => $response->getStatusCode()],
                ];
            }

            $data = $response->data;

            // Normalización muy básica; debe adaptarse a la estructura real de Verifik.
            $verificationId = $data['id'] ?? ($data['verification_id'] ?? null);
            $status = $data['status'] ?? 'desconocido';

            // Heurística simple: considerar "approved"/"valid"/"ok" como aprobado.
            $normalizedStatus = 'desconocido';
            if (is_string($status)) {
                $lower = strtolower($status);
                if (in_array($lower, ['approved', 'valid', 'ok', 'success'], true)) {
                    $normalizedStatus = 'aprobado';
                } elseif (in_array($lower, ['rejected', 'invalid', 'failed', 'error'], true)) {
                    $normalizedStatus = 'rechazado';
                }
            }

            return [
                'success' => $normalizedStatus !== 'rechazado',
                'status' => $normalizedStatus,
                'verification_id' => $verificationId,
                'message' => 'Respuesta recibida desde Verifik',
                'raw' => $data,
                'errors' => null,
            ];
        } catch (\Throwable $e) {
            Yii::error('Excepción llamando a Verifik: ' . $e->getMessage(), 'verifik');
            return [
                'success' => false,
                'status' => 'desconocido',
                'verification_id' => null,
                'message' => 'Excepción al llamar a Verifik: ' . $e->getMessage(),
                'raw' => null,
                'errors' => ['exception' => $e->getMessage()],
            ];
        }
    }
}

