<?php

namespace common\components\Domain\Clinical\HistoryExchange\Infrastructure\External\Contract;

use common\components\Domain\Clinical\HistoryExchange\Infrastructure\External\Dto\ClinicalHistoryExchangeStatusResult;
use common\models\Clinical\ClinicalHistoryOutboundJob;

/**
 * Conectores que pueden consultar acuse / estado de un envío ya aceptado.
 */
interface ClinicalHistorySubmissionStatusConnector
{
    public function pollSubmissionStatus(ClinicalHistoryOutboundJob $job): ClinicalHistoryExchangeStatusResult;
}
