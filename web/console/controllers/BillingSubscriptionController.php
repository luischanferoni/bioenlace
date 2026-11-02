<?php

namespace console\controllers;

use common\components\Domain\Organization\Billing\Application\Service\BillingCheckoutService;
use yii\console\Controller;
use yii\console\ExitCode;

/**
 * Procesos de mantenimiento de suscripciones y licencias.
 *
 * Cron sugerido: diario — `php yii billing-subscription/expire-paid-through`.
 */
class BillingSubscriptionController extends Controller
{
    public function actionExpirePaidThrough(): int
    {
        $expired = (new BillingCheckoutService())->expirePaidThroughSubscriptions();
        $this->stdout('Licencias suspendidas por período pagado vencido: ' . $expired . "\n");

        return ExitCode::OK;
    }
}
