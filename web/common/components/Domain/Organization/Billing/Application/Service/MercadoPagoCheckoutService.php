<?php

namespace common\components\Domain\Organization\Billing\Application\Service;

use Yii;
use yii\base\InvalidConfigException;

/**
 * Servicio de checkout con MercadoPago.
 *
 * Estructura básica para el plan alta-movil-cuentas-efectores.
 * Pendiente de pulir con integración completa.
 *
 * NOTAS:
 * - Esta es una estructura base; la integración completa requiere:
 *   - Definir modelo de precios, planes y recurrencia
 *   - Implementar webhooks firmados para confirmación de pago
 *   - Conciliación de intentos de pago para evitar duplicados
 *   - Gestión de suscripciones, renovación y cancelación
 *   - Moneda, impuestos, facturación
 *
 * - Para habilitar pagos reales:
 *   1. Configurar credenciales en params-local.php
 *   2. Configurar webhook URL en la consola de MercadoPago
 *   3. Implementar lógica de webhooks en MercadoPagoWebhookController
 *   4. Definir precios y planes en metadata o tabla catálogo
 *
 * @see web/docs/plans/alta-movil-cuentas-efectores/phases/03-altas-pagas.md
 */
class MercadoPagoCheckoutService
{
    /**
     * @var array Configuración de MercadoPago
     */
    private $config;

    /**
     * @var bool Modo sandbox/prueba
     */
    private $sandboxMode;

    public function __construct()
    {
        $this->config = Yii::$app->params['mercadoPago'] ?? [];
        $this->sandboxMode = $this->config['sandboxMode'] ?? true;

        if (empty($this->config['accessToken'])) {
            throw new InvalidConfigException('MercadoPago access token no configurado');
        }
    }

    /**
     * Crea una preferencia de pago para checkout.
     *
     * @param array $data Datos del checkout
     *   - idempotencyKey: clave única para evitar duplicados
     *   - planId: identificador del plan (consultorio, clínica, etc.)
     *   - metadata: datos adicionales (efector, usuario, etc.)
     * @return array Preferencia de pago con ID de MercadoPago
     */
    public function createPreference(array $data): array
    {
        // TODO: Implementar llamada real a API de MercadoPago
        // TODO: Validar idempotencyKey antes de crear preferencia
        // TODO: Calcular precio desde catálogo de planes
        // TODO: Configurar URLs de success/failure/pending

        if ($this->sandboxMode) {
            Yii::info(
                'MercadoPago sandbox: creando preferencia mockup para ' . json_encode($data),
                'mercadopago'
            );

            return [
                'id' => 'mock-' . ($data['idempotencyKey'] ?? 'unknown'),
                'sandbox_init_point' => 'https://sandbox.mercadopago.com/checkout/v1/redirect',
                'init_point' => 'https://www.mercadopago.com/checkout/v1/redirect',
                'auto_return' => 'approved',
                'external_reference' => $data['idempotencyKey'] ?? null,
            ];
        }

        // === INTEGRACIÓN REAL CON MERCADOPAGO (PENDIENTE) ===
        /*
        try {
            $client = new \yii\httpclient\Client();
            $response = $client->createRequest()
                ->setMethod('POST')
                ->setUrl($this->getApiUrl() . '/checkout/preferences')
                ->setHeaders([
                    'Authorization' => 'Bearer ' . $this->config['accessToken'],
                    'Content-Type' => 'application/json',
                ])
                ->setData([
                    'items' => [
                        [
                            'title' => $this->getPlanTitle($data['planId']),
                            'quantity' => 1,
                            'currency_id' => $this->config['currency'] ?? 'ARS',
                            'unit_price' => $this->getPlanPrice($data['planId']),
                        ],
                    ],
                    'back_urls' => [
                        'success' => $this->config['successUrl'],
                        'failure' => $this->config['failureUrl'],
                        'pending' => $this->config['pendingUrl'],
                    ],
                    'auto_return' => 'approved',
                    'external_reference' => $data['idempotencyKey'],
                    'metadata' => $data['metadata'] ?? [],
                ])
                ->send();

            if (!$response->isOk()) {
                Yii::error(
                    'MercadoPago API error: ' . $response->getStatusCode() . ' ' . $response->content,
                    'mercadopago'
                );
                throw new \RuntimeException('Error al crear preferencia de pago');
            }

            return $response->data;
        } catch (\Exception $e) {
            Yii::error(
                'MercadoPago connection error: ' . $e->getMessage(),
                'mercadopago'
            );
            throw $e;
        }
        */
        // === FIN INTEGRACIÓN REAL ===

        throw new \RuntimeException('Integración de MercadoPago no completada');
    }

    /**
     * Consulta el estado de un pago por ID de preferencia.
     *
     * @param string $preferenceId ID de preferencia de MercadoPago
     * @return array Estado del pago
     */
    public function getPaymentStatus(string $preferenceId): array
    {
        // TODO: Implementar consulta real a API de MercadoPago
        // TODO: Buscar por external_reference (idempotencyKey) en vez de preferenceId
        // TODO: Conciliar con intentos de pago persistidos

        if ($this->sandboxMode) {
            return [
                'status' => 'approved',
                'status_detail' => 'accredited',
                'external_reference' => $preferenceId,
            ];
        }

        // === INTEGRACIÓN REAL (PENDIENTE) ===
        throw new \RuntimeException('Integración de MercadoPago no completada');
    }

    /**
     * Procesa un webhook de MercadoPago.
     *
     * @param array $payload Datos del webhook
     * @return bool True si el webhook fue procesado correctamente
     */
    public function processWebhook(array $payload): bool
    {
        // TODO: Validar firma del webhook (X-Signature)
        // TODO: Deduplicar webhooks repetidos por ID
        // TODO: Actualizar estado de intento de pago
        // TODO: Activar licencia/entitlement solo tras confirmación
        // TODO: Reintentar webhooks fallidos con backoff

        if ($this->sandboxMode) {
            Yii::info(
                'MercadoPago sandbox: webhook mockup procesado',
                'mercadopago'
            );
            return true;
        }

        // === INTEGRACIÓN REAL (PENDIENTE) ===
        throw new \RuntimeException('Integración de MercadoPago no completada');
    }

    /**
     * Obtiene la URL base de la API según modo sandbox/producción.
     */
    private function getApiUrl(): string
    {
        return $this->sandboxMode
            ? 'https://api.mercadopago.com'
            : 'https://api.mercadopago.com';
    }

    /**
     * Obtiene el título de un plan desde catálogo.
     * TODO: Implementar catálogo de planes.
     */
    private function getPlanTitle(string $planId): string
    {
        $catalog = [
            'consultorio' => 'Consultorio propio',
            'clinica' => 'Clínica / Centro',
        ];
        return $catalog[$planId] ?? 'Plan Bioenlace';
    }

    /**
     * Obtiene el precio de un plan desde catálogo.
     * TODO: Implementar catálogo de planes con precios, impuestos, recurrencia.
     */
    private function getPlanPrice(string $planId): float
    {
        // Precios de ejemplo; definir en metadata o tabla
        $catalog = [
            'consultorio' => 10000.00,
            'clinica' => 50000.00,
        ];
        return $catalog[$planId] ?? 0.0;
    }
}
