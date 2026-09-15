<?php

namespace common\components\Domain\Clinical\Infrastructure\External\ClinicalHistory\Contract;

use common\components\Domain\Clinical\Infrastructure\External\ClinicalHistory\Dto\ClinicalHistoryExchangeStatusResult;
use common\models\Clinical\ClinicalHistoryOutboundJob;

/**
 * Conectores que pueden consultar acuse / estado de un envío ya aceptado.
 */
interface ClinicalHistorySubmissionStatusConnector
{
    public function pollSubmissionStatus(ClinicalHistoryOutboundJob $job): ClinicalHistoryExchangeStatusResult;
}
