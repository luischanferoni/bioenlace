<?php

namespace console\controllers;

use common\components\Domain\Clinical\CareCohort\Service\CareFollowupTouchpointProcessor;
use common\components\Domain\Clinical\CareCohort\Service\CarePackConfig;
use common\components\Domain\Clinical\CareCohort\Service\CarePackJobProcessor;
use common\models\Clinical\CarePackJob;
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
        $n = (new \common\components\Domain\Clinical\CareCohort\Batch\CarePackVertexBatchPoller())->poll((int) $limit);
        $this->stdout("care-pack poll-vertex: completed={$n}\n");

        return ExitCode::OK;
    }

    /**
     * Diagnóstico de configuración Vertex batch + contadores de cola.
     */
    public function actionVertexStatus(): int
    {
        $readiness = CarePackConfig::vertexBatchReadiness();
        $this->stdout("Vertex batch readiness: " . ($readiness['ok'] ? 'OK' : 'INCOMPLETO') . "\n");
        foreach ($readiness['config'] as $key => $value) {
            $this->stdout("  {$key}: " . (is_bool($value) ? ($value ? 'true' : 'false') : (string) $value) . "\n");
        }
        foreach ($readiness['warnings'] as $warning) {
            $this->stdout("WARN: {$warning}\n");
        }
        foreach ($readiness['errors'] as $error) {
            $this->stdout("ERROR: {$error}\n");
        }

        if (!CarePackConfig::isEnabled()) {
            return ExitCode::OK;
        }

        $pendingVertex = (int) CarePackJob::find()
            ->where(['status' => CarePackJob::STATUS_PENDING, 'mode' => CarePackJob::MODE_VERTEX_BATCH])
            ->count();
        $submitted = (int) CarePackJob::find()
            ->where(['status' => CarePackJob::STATUS_VERTEX_SUBMITTED])
            ->count();
        $pendingSync = (int) CarePackJob::find()
            ->where(['status' => CarePackJob::STATUS_PENDING, 'mode' => CarePackJob::MODE_SYNC])
            ->count();

        $this->stdout("Cola: pending_vertex={$pendingVertex} vertex_submitted={$submitted} pending_sync={$pendingSync}\n");
        $this->stdout(
            'Umbral batch: min_jobs=' . CarePackConfig::minJobsForVertex()
            . ' max_wait_min=' . CarePackConfig::maxWaitMinutesForVertex() . "\n"
        );

        return ExitCode::OK;
    }
}
