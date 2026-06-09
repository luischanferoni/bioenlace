<?php

namespace console\controllers;

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
        $this->stdout(sprintf(
            "care-pack: sync=%d vertex_submitted=%d vertex_completed=%d\n",
            $result['sync'],
            $result['vertex_submitted'],
            $result['vertex_completed']
        ));

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
