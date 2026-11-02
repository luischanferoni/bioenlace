<?php

namespace frontend\modules\api\v1\controllers\organization;

use common\components\Domain\Organization\Billing\Application\Service\BillingCheckoutService;
use frontend\modules\api\v1\controllers\BaseController;
use Yii;
use yii\web\BadRequestHttpException;
use yii\web\ServerErrorHttpException;
use yii\web\UnauthorizedHttpException;

/**
 * Recibe y procesa notificaciones firmadas de suscripciones y pagos de Mercado Pago.
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
     * @return array<string, string>
     * @throws BadRequestHttpException
     * @throws ServerErrorHttpException
     * @throws UnauthorizedHttpException
     */
    public function actionIndex(): array
    {
        $rawBody = Yii::$app->request->getRawBody();
        if ($rawBody === '') {
            throw new BadRequestHttpException('Payload vacío.');
        }
        $payload = json_decode($rawBody, true);
        if (!is_array($payload)) {
            throw new BadRequestHttpException('Payload inválido.');
        }

        try {
            (new BillingCheckoutService())->processWebhook(
                $payload,
                Yii::$app->request->headers->get('x-signature'),
                Yii::$app->request->headers->get('x-request-id')
            );
        } catch (\DomainException $e) {
            Yii::warning('Firma inválida en webhook de Mercado Pago.', 'mercadopago');
            throw new UnauthorizedHttpException('Firma de webhook inválida.');
        } catch (\InvalidArgumentException $e) {
            throw new BadRequestHttpException($e->getMessage());
        } catch (\Throwable $e) {
            Yii::error($e->getMessage() . "\n" . $e->getTraceAsString(), 'mercadopago');
            throw new ServerErrorHttpException('No se pudo procesar la notificación de Mercado Pago.');
        }

        return [
            'status' => 'ok',
            'message' => 'Notificación recibida.',
        ];
    }
}
