<?php

namespace common\components\Clinical\CareCohort\Batch;

use common\components\Clinical\CareCohort\CarePackContentParser;
use common\components\Clinical\CareCohort\Enum\CarePackType;
use common\components\Clinical\CareCohort\Service\CarePackConfig;
use common\components\Clinical\CareCohort\Service\CarePackRepository;
use common\models\Clinical\CareCohortPack;
use common\models\Clinical\CarePackJob;
use Yii;

/**
 * Poll de batchPredictionJobs y materialización de care_cohort_pack.
 *
 * Nota: la ruta exacta del JSONL de salida en GCS depende del layout de Vertex;
 * se intenta leer objetos listados en outputUriPrefix del job cuando está disponible.
 */
final class CarePackVertexBatchPoller
{
    private VertexBatchPredictionClient $vertex;
    private CarePackContentParser $parser;
    private CarePackRepository $repository;

    public function __construct(
        ?VertexBatchPredictionClient $vertex = null,
        ?CarePackContentParser $parser = null,
        ?CarePackRepository $repository = null
    ) {
        $this->vertex = $vertex ?? new VertexBatchPredictionClient();
        $this->parser = $parser ?? new CarePackContentParser();
        $this->repository = $repository ?? new CarePackRepository();
    }

    /**
     * @return int Jobs completados en este run
     */
    public function poll(int $limit = 20): int
    {
        if (!CarePackConfig::vertexBatchEnabled()) {
            return 0;
        }

        $jobNames = CarePackJob::find()
            ->select('vertex_batch_job_name')
            ->where(['status' => CarePackJob::STATUS_VERTEX_SUBMITTED])
            ->andWhere(['not', ['vertex_batch_job_name' => null]])
            ->distinct()
            ->limit($limit)
            ->column();

        $completed = 0;
        foreach ($jobNames as $jobName) {
            if (!is_string($jobName) || $jobName === '') {
                continue;
            }
            $completed += $this->pollJob($jobName);
        }

        return $completed;
    }

    private function pollJob(string $vertexJobName): int
    {
        $meta = $this->vertex->getJob($vertexJobName);
        if ($meta === null) {
            return 0;
        }

        $state = (string) ($meta['state'] ?? '');
        if ($state !== 'JOB_STATE_SUCCEEDED' && $state !== 'JOB_STATE_PARTIALLY_SUCCEEDED') {
            if ($state === 'JOB_STATE_FAILED' || $state === 'JOB_STATE_CANCELLED') {
                $this->failVertexJobs($vertexJobName, 'Vertex state: ' . $state);
            }

            return 0;
        }

        $outputLines = $this->resolveOutputLines($meta);
        if ($outputLines === []) {
            Yii::warning("Vertex batch sin líneas parseables: {$vertexJobName}", 'care-cohort');

            return 0;
        }

        $done = 0;
        foreach ($outputLines as $line) {
            $decoded = json_decode($line, true);
            if (!is_array($decoded)) {
                continue;
            }
            $customId = (string) ($decoded['custom_id'] ?? '');
            if ($customId === '') {
                continue;
            }

            $job = CarePackJob::findOne([
                'vertex_batch_job_name' => $vertexJobName,
                'vertex_batch_custom_id' => $customId,
            ]);
            if (!$job instanceof CarePackJob) {
                continue;
            }

            $text = $this->extractResponseText($decoded);
            $content = $this->parser->parse($text);
            if ($content === null) {
                $this->markJobFailed($job, 'JSON inválido en salida Vertex');

                continue;
            }

            $profile = json_decode((string) $job->cohort_profile_json, true);
            if (!is_array($profile)) {
                $profile = [];
            }

            $pack = $this->repository->savePack(
                $job->pack_type,
                $job->cohort_key,
                $profile,
                $content,
                CarePackType::iaContext($job->pack_type),
                CareCohortPack::SOURCE_VERTEX_BATCH
            );

            $job->status = CarePackJob::STATUS_COMPLETED;
            $job->pack_id = (int) $pack->id;
            $job->last_error = null;
            $job->updated_at = date('Y-m-d H:i:s');
            $job->save(false);
            if ((int) $job->encounter_id > 0) {
                $this->repository->attachPackToEncounter((int) $job->encounter_id, $job->pack_type, (int) $pack->id);
            }
            CarePackVertexBatchTelemetry::registrarLineaCompletada($decoded);
            $done++;
        }

        return $done;
    }

    /**
     * @param array<string, mixed> $meta
     * @return list<string>
     */
    private function resolveOutputLines(array $meta): array
    {
        $prefix = $meta['outputConfig']['gcsDestination']['outputUriPrefix'] ?? null;
        if (!is_string($prefix) || strpos($prefix, 'gs://') !== 0) {
            return [];
        }

        $path = substr($prefix, strlen('gs://'));
        $slash = strpos($path, '/');
        if ($slash === false) {
            return [];
        }
        $bucket = substr($path, 0, $slash);
        $objectPrefix = substr($path, $slash + 1);

        $uploader = new GcsSimpleUploader();
        $lines = $uploader->downloadJsonlLinesUnderPrefix($bucket, $objectPrefix);
        if ($lines !== []) {
            return $lines;
        }

        $fallbackObject = rtrim($objectPrefix, '/') . '/predictions.jsonl';
        $raw = $uploader->downloadString($bucket, $fallbackObject);
        if ($raw === null || trim($raw) === '') {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode("\n", $raw))));
    }

    /**
     * @param array<string, mixed> $decoded
     */
    private function extractResponseText(array $decoded): string
    {
        if (isset($decoded['response']['candidates'][0]['content']['parts'][0]['text'])) {
            return (string) $decoded['response']['candidates'][0]['content']['parts'][0]['text'];
        }
        if (isset($decoded['response']['text'])) {
            return (string) $decoded['response']['text'];
        }

        return json_encode($decoded['response'] ?? $decoded, JSON_UNESCAPED_UNICODE);
    }

    private function failVertexJobs(string $vertexJobName, string $reason): void
    {
        $rows = CarePackJob::find()
            ->where(['vertex_batch_job_name' => $vertexJobName, 'status' => CarePackJob::STATUS_VERTEX_SUBMITTED])
            ->all();
        foreach ($rows as $job) {
            $this->markJobFailed($job, $reason);
        }
    }

    private function markJobFailed(CarePackJob $job, string $reason): void
    {
        $job->attempts = (int) $job->attempts + 1;
        $job->last_error = $reason;
        $job->status = $job->attempts >= 5 ? CarePackJob::STATUS_FAILED : CarePackJob::STATUS_PENDING;
        $job->mode = CarePackJob::MODE_SYNC;
        $job->updated_at = date('Y-m-d H:i:s');
        $job->save(false);
    }
}
