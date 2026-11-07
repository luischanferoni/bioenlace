<?php

namespace console\controllers;

use common\components\Domain\Organization\Billing\Application\Service\BillingCheckoutService;
use common\components\Domain\Organization\Billing\Infrastructure\External\MercadoPago\MercadoPagoApiClient;
use Yii;
use yii\console\Controller;
use yii\console\ExitCode;

/**
 * Procesos de mantenimiento de suscripciones y licencias.
 *
 * Cron sugerido: diario — `php yii billing-subscription/expire-paid-through`.
 */
class BillingSubscriptionController extends Controller
{
    /**
     * @param string $actionID
     * @return string[]
     */
    public function options($actionID): array
    {
        return array_merge(parent::options($actionID), ['userId']);
    }

    /**
     * @return array<string, string>
     */
    public function optionAliases(): array
    {
        return array_merge(parent::optionAliases(), ['user-id' => 'userId']);
    }

    public function actionExpirePaidThrough(): int
    {
        $expired = (new BillingCheckoutService())->expirePaidThroughSubscriptions();
        $this->stdout('Licencias suspendidas por período pagado vencido: ' . $expired . "\n");

        return ExitCode::OK;
    }

    /**
     * Diagnóstico sandbox: muestra site/email del collector y, si se indica
     * `--user-id`, del pagador de prueba. Sirve para saber qué `payer_email`
     * usar en `mercadoPago.sandboxBuyerEmail` y confirmar que coincide el site.
     *
     *   php yii billing-subscription/mp-test-payer
     *   php yii billing-subscription/mp-test-payer --user-id=3817520526
     */
    public function actionMpTestPayer(string $userId = ''): int
    {
        $token = trim((string) (Yii::$app->params['mercadoPago']['accessToken'] ?? ''));
        if ($token === '') {
            $this->stderr("No hay mercadoPago.accessToken configurado.\n");

            return ExitCode::CONFIG;
        }

        $client = new MercadoPagoApiClient($token);
        try {
            $this->stdout('Collector: ' . json_encode($this->userSummary($client->getMe()), JSON_UNESCAPED_UNICODE) . "\n");
            if ($userId !== '') {
                $this->stdout('Payer: ' . json_encode($this->userSummary($client->getUser($userId)), JSON_UNESCAPED_UNICODE) . "\n");
            }
        } catch (\Throwable $e) {
            $this->stderr($e->getMessage() . "\n");

            return ExitCode::UNSPECIFIED_ERROR;
        }

        return ExitCode::OK;
    }

    /**
     * @param array<string, mixed> $user
     * @return array<string, mixed>
     */
    private function userSummary(array $user): array
    {
        return array_intersect_key($user, array_flip(['id', 'site_id', 'email', 'username', 'nickname', 'tags']));
    }
}
