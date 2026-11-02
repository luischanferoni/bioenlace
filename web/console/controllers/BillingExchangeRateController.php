<?php

namespace console\controllers;

use common\components\Domain\Organization\Billing\Application\Service\BillingExchangeRateService;
use yii\console\Controller;
use yii\console\ExitCode;

/**
 * Maintains the effective-dated exchange-rate history used for commercial quotes.
 */
class BillingExchangeRateController extends Controller
{
    public function actionSet(float $rate, string $effectiveDate = '', string $source = 'MEP vendedor'): int
    {
        $effectiveDate = $effectiveDate !== '' ? $effectiveDate : date('Y-m-d');
        $record = (new BillingExchangeRateService())->record($rate, $effectiveDate, $source);
        $this->stdout(sprintf(
            "Cotización registrada: 1 USD = %.4f ARS desde %s (%s).\n",
            (float) $record->rate,
            $record->effective_date,
            $record->source
        ));

        return ExitCode::OK;
    }
}
