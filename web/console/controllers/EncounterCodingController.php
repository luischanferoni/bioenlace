<?php

namespace console\controllers;

use common\components\Domain\Clinical\Encounter\Application\Service\EncounterCodingJobService;
use common\models\Clinical\EncounterCodingJob;
use yii\console\Controller;
use yii\console\ExitCode;

/**
 * Worker de codificación clínica diferida.
 *
 * Cron: php yii encounter-coding/run 30
 * Estado: php yii encounter-coding/status [--id=]
 * Reintento: php yii encounter-coding/retry <id>
 */
class EncounterCodingController extends Controller
{
    public function actionRun($limit = 30): int
    {
        $result = (new EncounterCodingJobService())->run((int) $limit);
        $this->stdout(sprintf(
            "encounter-coding: claimed=%d done=%d failed=%d skipped=%d\n",
            $result['claimed'],
            $result['done'],
            $result['failed'],
            $result['skipped']
        ));

        return ExitCode::OK;
    }

    public function actionStatus($id = null): int
    {
        if ($id !== null) {
            $job = EncounterCodingJob::findOne((int) $id);
            if ($job === null) {
                $this->stderr("No existe el job {$id}.\n");

                return ExitCode::UNSPECIFIED_ERROR;
            }
            $this->printJob($job);

            return ExitCode::OK;
        }

        $counts = EncounterCodingJob::find()
            ->select(['status', 'total' => 'COUNT(*)'])
            ->groupBy('status')
            ->asArray()
            ->all();
        foreach ($counts as $row) {
            $this->stdout(sprintf("%-10s %d\n", (string) $row['status'], (int) $row['total']));
        }

        return ExitCode::OK;
    }

    public function actionRetry($id): int
    {
        $job = EncounterCodingJob::findOne((int) $id);
        if ($job === null) {
            $this->stderr("No existe el job {$id}.\n");

            return ExitCode::UNSPECIFIED_ERROR;
        }

        $job->status = EncounterCodingJob::STATUS_PENDING;
        $job->attempts = 0;
        $job->scheduled_at = date('Y-m-d H:i:s');
        $job->last_error = null;
        $job->finished_at = null;
        $job->updated_at = date('Y-m-d H:i:s');
        $job->save(false, ['status', 'attempts', 'scheduled_at', 'last_error', 'finished_at', 'updated_at']);

        $this->stdout("Job {$id} reencolado.\n");

        return ExitCode::OK;
    }

    private function printJob(EncounterCodingJob $job): void
    {
        $this->stdout(sprintf(
            "id=%d encounter=%d status=%s attempts=%d scheduled=%s started=%s finished=%s\n",
            $job->id,
            $job->encounter_id,
            $job->status,
            $job->attempts,
            $job->scheduled_at,
            $job->started_at ?? '—',
            $job->finished_at ?? '—'
        ));
        if ($job->last_error !== null && $job->last_error !== '') {
            $this->stdout('error: ' . $job->last_error . "\n");
        }
    }
}
