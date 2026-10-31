<?php

namespace frontend\modules\api\v1\controllers\organization;

use common\components\Domain\Organization\Billing\Application\Service\MercadoPagoCheckoutService;
use frontend\modules\api\v1\controllers\BaseController;
use Yii;
use yii\web\BadRequestHttpException;

/**
 * Controlador API v1 para recibir webhooks de MercadoPago.
 *
 * Estructura básica para el plan alta-movil-cuentas-efectores.
 * Pendiente de pulir con integración completa.
 *
 * NOTAS:
 * - Este controlador recibe notificaciones de pago de MercadoPago
 * - Debe validar la firma del webhook (X-Signature)
 * - Debe deduplicar webhooks repetidos
 * - Debe actualizar el estado del intento de pago
 * - Debe activar la licencia/entitlement solo tras confirmación
 *
 * @see web/docs/plans/alta-movil-cuentas-efectores/phases/03-altas-pagas.md
 */
class MercadoPagoWebhookController extends BaseController
{
    public $enableCsrfValidation = false;

    public static $authenticatorExcept = [
        'index',
    ];

    public function init()
    {
        parent::init();
        $this->contractResponses = true;
    }

    /**
     * Acción para recibir webhooks de MercadoPago.
     *
     * @return array
     * @throws BadRequestHttpException
     */
    public function actionIndex()
    {
        $rawBody = Yii::$app->request->getRawBody();
        if (empty($rawBody)) {
            throw new BadRequestHttpException('Payload vacío');
        }

        $payload = json_decode($rawBody, true);
        if (!is_array($payload)) {
            throw new BadRequestHttpException('Payload inválido');
        }

        // TODO: Validar firma del webhook (X-Signature header)
        // $signature = Yii::$app->request->headers->get('X-Signature');
        // $this->validateWebhookSignature($signature, $rawBody);

        try {
            $service = new MercadoPagoCheckoutService();
            $processed = $service->processWebhook($payload);

            if ($processed) {
                return [
                    'status' => 'ok',
                    'message' => 'Webhook procesado',
                ];
            }

            return [
                'status' => 'error',
                'message' => 'Webhook no procesado',
            ];
        } catch (\Exception $e) {
            Yii::error(
                'Error procesando webhook MercadoPago: ' . $e->getMessage(),
                'mercadopago'
            );
            throw new BadRequestHttpException('Error procesando webhook');
        }
    }

    /**
     * Valida la firma del webhook de MercadoPago.
     *
     * @param string|null $signature Header X-Signature
     * @param string $rawBody Payload crudo
     * @throws BadRequestHttpException
     */
    private function validateWebhookSignature(?string $signature, string $rawBody): void
    {
        // TODO: Implementar validación de firma según documentación de MercadoPago
        // Usar HMAC-SHA256 con webhookSecret configurado en params
        /*
        $secret = Yii::$app->params['mercadoPago']['webhookSecret'] ?? null;
        if (!$secret) {
            throw new BadRequestHttpException('Webhook secret no configurado');
        }

        $expected = hash_hmac('sha256', $rawBody, $secret);
        if (!hash_equals($expected, $signature)) {
            throw new BadRequestHttpException('Firma inválida');
        }
        */
    }
}
