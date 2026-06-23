<?php

namespace common\components\Domain\Integrations\ClinicalHistory\Contract;

use common\components\Domain\Integrations\ClinicalHistory\Dto\ClinicalHistoryExchangeStatusResult;
use common\models\Clinical\ClinicalHistoryOutboundJob;

/**
 * Conectores que pueden consultar acuse / estado de un envío ya aceptado.
 */
interface ClinicalHistorySubmissionStatusConnector
{
    public function pollSubmissionStatus(ClinicalHistoryOutboundJob $job): ClinicalHistoryExchangeStatusResult;
}
