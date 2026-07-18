<?php

namespace console\controllers;

use common\components\Domain\Scheduling\Service\TurnoAdvanceOfferAgent;
use yii\console\Controller;
use yii\console\ExitCode;

/**
 * Campañas de adelantamiento por cancelación.
 *
 * Uso:
 *   php yii turno-advance-offer/run [--limit=50]
 *   php yii turno-advance-offer/repair [--limit=50]
 */
class TurnoAdvanceOfferController extends Controller
{
    public $limit = 50;

    public function options($actionID)
    {
        return array_merge(parent::options($actionID), ['limit']);
    }

    public function actionRun(): int
    {
        $result = (new TurnoAdvanceOfferAgent())->processDue((int) $this->limit);
        $this->stdout(sprintf(
            "advance-offer processed=%d offered=%d closed=%d\n",
            $result['processed'],
            $result['offered'],
            $result['closed']
        ));

        return ExitCode::OK;
    }

    public function actionRepair(): int
    {
        $result = (new TurnoAdvanceOfferAgent())->repairMissingCampaigns((int) $this->limit);
        $this->stdout(sprintf(
            "advance-offer repair scanned=%d started=%d\n",
            $result['scanned'],
            $result['started']
        ));

        return ExitCode::OK;
    }
}
