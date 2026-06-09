<?php

namespace common\components\Clinical\CareCohort\Service;

use common\components\Clinical\CareCohort\Batch\CarePackVertexBatchSubmitter;
use common\components\Clinical\CareCohort\Batch\CarePackVertexBatchPoller;
use common\models\Clinical\CarePackJob;
use common\models\Clinical\Encounter;

final class CarePackJobProcessor
{
    private CarePackGenerationService $generator;
    private CarePackVertexBatchSubmitter $vertexSubmitter;
    private CarePackVertexBatchPoller $vertexPoller;

    public function __construct(
        ?CarePackGenerationService $generator = null,
        ?CarePackVertexBatchSubmitter $vertexSubmitter = null,
        ?CarePackVertexBatchPoller $vertexPoller = null
    ) {
        $this->generator = $generator ?? new CarePackGenerationService();
        $this->vertexSubmitter = $vertexSubmitter ?? new CarePackVertexBatchSubmitter();
        $this->vertexPoller = $vertexPoller ?? new CarePackVertexBatchPoller();
    }

    /**
     * @return array{sync: int, vertex_submitted: int, vertex_completed: int}
     */
    public function run(int $syncLimit = 30): array
    {
        if (!CarePackConfig::isEnabled()) {
            return ['sync' => 0, 'vertex_submitted' => 0, 'vertex_completed' => 0];
        }

        $vertexSubmitted = $this->vertexSubmitter->submitPending();
        $vertexCompleted = $this->vertexPoller->poll();

        $syncDone = 0;
        $jobs = CarePackJob::find()
            ->where([
                'status' => CarePackJob::STATUS_PENDING,
                'mode' => CarePackJob::MODE_SYNC,
            ])
            ->andWhere(['<=', 'run_at', date('Y-m-d H:i:s')])
            ->orderBy(['run_at' => SORT_ASC])
            ->limit($syncLimit)
            ->all();

        foreach ($jobs as $job) {
            /** @var CarePackJob $job */
            $job->status = CarePackJob::STATUS_RUNNING;
            $job->updated_at = date('Y-m-d H:i:s');
            $job->save(false);

            $encounter = null;
            if ((int) $job->encounter_id > 0) {
                $encounter = Encounter::findOne(['id' => (int) $job->encounter_id, 'deleted_at' => null]);
            }

            if ($this->generator->processJob($job, $encounter)) {
                $syncDone++;
            }
        }

        return [
            'sync' => $syncDone,
            'vertex_submitted' => $vertexSubmitted,
            'vertex_completed' => $vertexCompleted,
        ];
    }
}
