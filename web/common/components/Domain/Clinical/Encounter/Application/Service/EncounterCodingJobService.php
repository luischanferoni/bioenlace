<?php

namespace common\components\Domain\Clinical\Encounter\Application\Service;

use common\components\Domain\Clinical\Encounter\Application\Coding\EncounterCodingTargetRegistry;
use common\models\Clinical\Encounter;
use common\models\Clinical\EncounterCodingJob;
use Yii;

/**
 * Cola de codificación clínica: encola al guardar y procesa en background.
 *
 * El encounter se persiste sin códigos; el worker corre todos los targets
 * codificables (diagnóstico, motivos, medicación y pedidos) y persiste.
 */
final class EncounterCodingJobService
{
    /**
     * Encola un encounter. Idempotente por `encounter_id`.
     *
     * @param list<string> $diagnosisTerms términos de diagnóstico sin código
     */
    public function enqueue(Encounter $encounter, array $diagnosisTerms = [], int $delaySeconds = 0): bool
    {
        $encounterId = (int) $encounter->id;
        if ($encounterId <= 0) {
            return false;
        }
        if (EncounterCodingJob::find()->where(['encounter_id' => $encounterId])->exists()) {
            return false;
        }

        $now = date('Y-m-d H:i:s');
        $job = new EncounterCodingJob();
        $job->encounter_id = $encounterId;
        $job->diagnosis_terms_json = $diagnosisTerms === []
            ? null
            : (string) json_encode(array_values($diagnosisTerms), JSON_UNESCAPED_UNICODE);
        $job->status = EncounterCodingJob::STATUS_PENDING;
        $job->attempts = 0;
        $job->scheduled_at = date('Y-m-d H:i:s', time() + max(0, $delaySeconds));
        $job->created_at = $now;
        $job->updated_at = $now;

        try {
            return $job->save(false);
        } catch (\Throwable $e) {
            // Carrera con otro guardado: el UNIQUE evita duplicados.
            Yii::warning('EncounterCodingJobService::enqueue: ' . $e->getMessage(), 'encounter-coding');

            return false;
        }
    }

    /**
     * @return array{claimed:int,done:int,failed:int,skipped:int}
     */
    public function run(int $limit = 30): array
    {
        $this->recoverStale();
        $claimed = $this->claim(max(1, $limit));
        $done = 0;
        $failed = 0;
        $skipped = 0;

        foreach ($claimed as $jobId) {
            $job = EncounterCodingJob::findOne($jobId);
            if ($job === null) {
                $skipped++;
                continue;
            }
            $encounter = Encounter::findOne((int) $job->encounter_id);
            if ($encounter === null) {
                $job->markFailed('Encounter inexistente: ' . $job->encounter_id);
                $failed++;
                continue;
            }

            try {
                $context = ['diagnosis_terms' => $job->diagnosisTerms()];
                foreach (EncounterCodingTargetRegistry::all() as $target) {
                    $target->code($encounter, $context);
                }
                $job->markDone();
                $done++;
            } catch (\Throwable $e) {
                $job->markFailed($e->getMessage());
                $failed++;
            }
        }

        return ['claimed' => count($claimed), 'done' => $done, 'failed' => $failed, 'skipped' => $skipped];
    }

    /**
     * Reencola jobs RUNNING que quedaron colgados (worker caído).
     */
    private function recoverStale(): int
    {
        $now = date('Y-m-d H:i:s');
        $cutoff = date('Y-m-d H:i:s', time() - 900);

        return EncounterCodingJob::updateAll(
            ['status' => EncounterCodingJob::STATUS_PENDING, 'scheduled_at' => $now, 'updated_at' => $now],
            ['and', ['status' => EncounterCodingJob::STATUS_RUNNING], ['<', 'started_at', $cutoff]]
        );
    }

    /**
     * @return list<int>
     */
    private function claim(int $limit): array
    {
        $now = date('Y-m-d H:i:s');
        $ids = EncounterCodingJob::find()
            ->select('id')
            ->where(['status' => EncounterCodingJob::STATUS_PENDING])
            ->andWhere(['<=', 'scheduled_at', $now])
            ->orderBy(['id' => SORT_ASC])
            ->limit($limit)
            ->column();

        $claimed = [];
        foreach ($ids as $id) {
            $id = (int) $id;
            $affected = EncounterCodingJob::updateAll(
                ['status' => EncounterCodingJob::STATUS_RUNNING, 'started_at' => $now, 'updated_at' => $now],
                ['id' => $id, 'status' => EncounterCodingJob::STATUS_PENDING]
            );
            if ($affected === 1) {
                EncounterCodingJob::updateAllCounters(['attempts' => 1], ['id' => $id]);
                $claimed[] = $id;
            }
        }

        return $claimed;
    }
}
