<?php

namespace console\controllers;

use common\components\Clinical\CareCohort\Service\CareFollowupTouchpointProcessor;
use common\components\Clinical\CareCohort\Service\CarePackJobProcessor;
use yii\console\Controller;
use yii\console\ExitCode;

/**
 * Cola de generación de packs de cohorte (sync + Vertex batch).
 *
 * Cron sugerido: php yii care-pack/run-jobs
 */
class CarePackController extends Controller
{
    /**
     * Procesa jobs sync y envía/poll Vertex batch según configuración.
     *
     * @param int $limit Máximo de jobs sync por ejecución
     */
    public function actionRunJobs($limit = 30): int
    {
        $result = (new CarePackJobProcessor())->run((int) $limit);
        $followups = (new CareFollowupTouchpointProcessor())->processDue((int) $limit);
        $this->stdout(sprintf(
            "care-pack: sync=%d vertex_submitted=%d vertex_completed=%d followup_notified=%d\n",
            $result['sync'],
            $result['vertex_submitted'],
            $result['vertex_completed'],
            $followups
        ));

        return ExitCode::OK;
    }

    /**
     * Solo touchpoints de seguimiento vencidos (push al paciente).
     *
     * @param int $limit Máximo de touchpoints por ejecución
     */
    public function actionProcessFollowups($limit = 50): int
    {
        $n = (new CareFollowupTouchpointProcessor())->processDue((int) $limit);
        $this->stdout("care-pack process-followups: notified={$n}\n");

        return ExitCode::OK;
    }

    /** Solo poll de jobs Vertex ya enviados. */
    public function actionPollVertex($limit = 20): int
    {
        $n = (new \common\components\Clinical\CareCohort\Batch\CarePackVertexBatchPoller())->poll((int) $limit);
        $this->stdout("care-pack poll-vertex: completed={$n}\n");

        return ExitCode::OK;
    }
}
