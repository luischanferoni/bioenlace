<?php

namespace common\components\Domain\Person\Identity\Infrastructure\External\Sisa;

use Yii;
use yii\base\Component;

/**
 * Adaptador mockup del componente SISA para verificación REFEPS.
 *
 * NOTA: Esta es una implementación mockup para desarrollo.
 * La clase real de integración con SISA debe configurarse en producción.
 *
 * Comportamiento actual:
 * - Simula una respuesta exitosa de REFEPS para cualquier profesional
 * - La conexión real con la API de SISA está comentada
 * - Asumimos siempre que la verificación es OK
 *
 * Para habilitar la integración real:
 * 1. Descomentar el bloque de conexión con la API de SISA
 * 2. Configurar las credenciales en params-local.php
 * 3. Implementar la lógica de parsing de respuesta real
 * 4. Quitar el flag 'mock' del parámetro sisa.mockMode
 *
 * @see RefepsProfessionalVerificationService
 * @see https://docs.sisa.msal.gob.ar/
 */
class SisaAdapter extends Component
{
    /**
     * @var array|null Credenciales configuradas en params
     */
    private $config;

    /**
     * @var bool Modo mockup (true = siempre responde OK)
     */
    private $mockMode;

    public function __construct($config = [])
    {
        $this->config = $config;
        $this->mockMode = Yii::$app->params['sisa']['mockMode'] ?? true;
    }

    /**
     * Consulta profesionales en el registro de Santiago (REFEPS).
     *
     * En modo mockup, devuelve siempre un profesional con el documento solicitado.
     * Para integración real, descomentar el bloque de conexión HTTP.
     *
     * @param string $apellido Apellido del profesional
     * @param string $nombre Nombre del profesional
     * @param string $matricula Matrícula (opcional)
     * @param string $documento DNI del profesional
     * @return array|string Respuesta JSON de SISA o array estructurado
     */
    public function getProfesionalesDeSantiago($apellido, $nombre, $matricula = '', $documento = '')
    {
        if ($this->mockMode) {
            Yii::info(
                'SISA mockup: respondiendo OK para profesional ' .
                "apellido=$apellido, nombre=$nombre, documento=$documento",
                'sisa'
            );

            // Respuesta mockup que pasa la validación de RefepsProfessionalVerificationService
            return [
                'ok' => true,
                'total' => 1,
                'profesionales' => [
                    [
                        'dni' => $documento,
                        'apellido' => $apellido,
                        'nombre' => $nombre,
                        'matricula' => $matricula ?: 'MOCK-' . $documento,
                    ],
                ],
            ];
        }

        // === INTEGRACIÓN REAL CON SISA (COMENTADA) ===
        // Para habilitar:
        // 1. Descomentar este bloque
        // 2. Configurar credenciales en params-local.php
        // 3. Verificar endpoints y formato de respuesta según documentación SISA
        /*
        try {
            $appId = $this->config['appId'] ?? Yii::$app->params['SISA_APP_ID'];
            $appKey = $this->config['appKey'] ?? Yii::$app->params['SISA_APP_KEY'];
            $baseUrl = $this->config['baseUrl'] ?? 'https://sisa.msal.gob.ar';

            $client = new \yii\httpclient\Client();
            $response = $client->createRequest()
                ->setMethod('GET')
                ->setUrl($baseUrl . '/profesionales/santiago')
                ->setHeaders([
                    'X-App-Id' => $appId,
                    'X-App-Key' => $appKey,
                ])
                ->setData([
                    'apellido' => $apellido,
                    'nombre' => $nombre,
                    'matricula' => $matricula,
                    'documento' => $documento,
                ])
                ->send();

            if (!$response->isOk()) {
                Yii::error(
                    'SISA API error: ' . $response->getStatusCode() . ' ' . $response->content,
                    'sisa'
                );
                return ['ok' => false, 'error' => 'Error en la consulta'];
            }

            return $response->data;
        } catch (\Exception $e) {
            Yii::error(
                'SISA connection error: ' . $e->getMessage(),
                'sisa'
            );
            return ['ok' => false, 'error' => 'Error de conexión'];
        }
        */
        // === FIN INTEGRACIÓN REAL ===

        // Si llegamos aquí y no estamos en mock mode, algo está mal configurado
        Yii::warning('SISA no está en modo mock pero la integración real está comentada', 'sisa');
        return ['ok' => false, 'error' => 'Integración no configurada'];
    }
}
