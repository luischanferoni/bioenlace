<?php

namespace common\components\Domain\Clinical\CareCohort\Batch;

use common\components\Domain\Clinical\CareCohort\Service\CarePackConfig;
use common\components\Domain\Clinical\CareCohort\Service\CarePackPromptBuilder;
use common\models\Clinical\CarePackJob;
use common\models\Clinical\Encounter;
use Yii;

/**
 * Agrupa jobs pending vertex_batch, sube JSONL a GCS y crea batchPredictionJob.
 */
final class CarePackVertexBatchSubmitter
{
    private CarePackPromptBuilder $prompts;
    private GcsSimpleUploader $uploader;
    private VertexBatchPredictionClient $vertex;

    public function __construct(
        ?CarePackPromptBuilder $prompts = null,
        ?GcsSimpleUploader $uploader = null,
        ?VertexBatchPredictionClient $vertex = null
    ) {
        $this->prompts = $prompts ?? new CarePackPromptBuilder();
        $this->uploader = $uploader ?? new GcsSimpleUploader();
        $this->vertex = $vertex ?? new VertexBatchPredictionClient();
    }

    /**
     * @return int Jobs enviados a Vertex en este run
     */
    public function submitPending(int $limit = 200): int
    {
        if (!CarePackConfig::vertexBatchEnabled()) {
            return 0;
        }

        $cfg = CarePackConfig::vertexBatch();
        $bucket = trim((string) ($cfg['gcs_bucket'] ?? ''));
        $inputPrefix = trim((string) ($cfg['gcs_input_prefix'] ?? 'care-batch/input/'), '/') . '/';
        $outputPrefix = trim((string) ($cfg['gcs_output_prefix'] ?? 'care-batch/output/'), '/') . '/';

        $jobs = CarePackJob::find()
            ->where([
                'status' => CarePackJob::STATUS_PENDING,
                'mode' => CarePackJob::MODE_VERTEX_BATCH,
            ])
            ->andWhere(['<=', 'run_at', date('Y-m-d H:i:s')])
            ->orderBy(['run_at' => SORT_ASC])
            ->limit($limit)
            ->all();

        if (!$this->shouldSubmitBatch($jobs)) {
            return 0;
        }

        $batchId = 'batch-' . date('YmdHis') . '-' . bin2hex(random_bytes(4));
        $objectName = $inputPrefix . $batchId . '.jsonl';
        $lines = [];
        $customMap = [];

        foreach ($jobs as $job) {
            /** @var CarePackJob $job */
            $profile = json_decode((string) $job->cohort_profile_json, true);
            if (!is_array($profile)) {
                $profile = [];
            }
            $encounter = null;
            if ((int) $job->encounter_id > 0) {
                $encounter = Encounter::findOne(['id' => (int) $job->encounter_id, 'deleted_at' => null]);
            }
            $subjectId = (int) ($job->subject_persona_id ?? 0);
            if ($subjectId <= 0 && $encounter !== null) {
                $subjectId = (int) $encounter->subject_persona_id;
            }
            if ($subjectId <= 0) {
                continue;
            }

            $customId = 'job-' . $job->id;
            $prompt = $this->prompts->build($job->pack_type, $profile, $subjectId, $encounter);
            $lines[] = json_encode([
                'custom_id' => $customId,
                'request' => [
                    'contents' => [
                        [
                            'role' => 'user',
                            'parts' => [['text' => $prompt]],
                        ],
                    ],
                    'generationConfig' => [
                        'maxOutputTokens' => 4096,
                        'temperature' => 0.3,
                        'responseMimeType' => 'application/json',
                    ],
                ],
            ], JSON_UNESCAPED_UNICODE);
            $customMap[$customId] = $job;
            $job->vertex_batch_custom_id = $customId;
            $job->prompt_snapshot = $prompt;
            $job->status = CarePackJob::STATUS_RUNNING;
            $job->updated_at = date('Y-m-d H:i:s');
            $job->save(false);
        }

        if ($lines === []) {
            return 0;
        }

        $jsonl = implode("\n", $lines) . "\n";
        if (!$this->uploader->uploadString($bucket, $objectName, $jsonl)) {
            foreach ($customMap as $job) {
                $job->status = CarePackJob::STATUS_PENDING;
                $job->save(false);
            }

            return 0;
        }

        $inputUri = 'gs://' . $bucket . '/' . $objectName;
        $outputUri = 'gs://' . $bucket . '/' . $outputPrefix . $batchId;
        $created = $this->vertex->createGeminiBatchJob($inputUri, $outputUri);
        if ($created === null || empty($created['name'])) {
            foreach ($customMap as $job) {
                $job->status = CarePackJob::STATUS_PENDING;
                $job->save(false);
            }

            return 0;
        }

        $jobName = (string) $created['name'];
        foreach ($customMap as $job) {
            $job->vertex_batch_job_name = $jobName;
            $job->status = CarePackJob::STATUS_VERTEX_SUBMITTED;
            $job->updated_at = date('Y-m-d H:i:s');
            $job->save(false);
        }

        Yii::info("Vertex batch care-pack enviado: {$jobName} jobs=" . count($customMap), 'care-cohort');

        return count($customMap);
    }

    /**
     * @param list<CarePackJob> $jobs
     */
    private function shouldSubmitBatch(array $jobs): bool
    {
        $count = count($jobs);
        if ($count === 0) {
            return false;
        }
        if ($count >= CarePackConfig::minJobsForVertex()) {
            return true;
        }

        $maxWait = CarePackConfig::maxWaitMinutesForVertex();
        if ($maxWait <= 0) {
            return false;
        }

        $oldest = null;
        foreach ($jobs as $job) {
            $ts = strtotime((string) $job->run_at);
            if ($ts !== false && ($oldest === null || $ts < $oldest)) {
                $oldest = $ts;
            }
        }
        if ($oldest === null) {
            return false;
        }

        return (time() - $oldest) >= ($maxWait * 60);
    }
}
