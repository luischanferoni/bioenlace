<?php

namespace common\components\Domain\Clinical\Encounter\Application\Service;

use common\components\Domain\Clinical\Encounter\Application\Coding\EncounterCodingTargetRegistry;
use common\components\Domain\Clinical\Encounter\Domain\EncounterStatus;
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
     * Ventana de espera antes de codificar, en segundos.
     *
     * Se configura con `encounter_coding_delay_hours` (default 48 h): deja que
     * el encounter se termine de editar antes de codificar.
     */
    public static function delaySecondsFromParams(): int
    {
        $hours = (int) (Yii::$app->params['encounter_coding_delay_hours'] ?? 48);

        return max(0, $hours) * 3600;
    }

    /**
     * Encola o reencola un encounter. Idempotente por `encounter_id`.
     *
     * Una edición crea un encounter nuevo y anula el anterior, así que en la
     * práctica cada encounter obtiene su propio job. El upsert por
     * `encounter_id` queda como red de seguridad si se reutilizara el mismo id.
     *
     * - Sin job: crea PENDING para `now + delay`.
     * - PENDING: actualiza términos y pospone la fecha.
     * - DONE / FAILED / SKIPPED: vuelve a PENDING con intentos en cero.
     * - RUNNING: no interrumpe; marca `rerun_requested` para reencolar al terminar.
     *
     * @param list<string> $diagnosisTerms términos de diagnóstico sin código
     */
    public function enqueue(Encounter $encounter, array $diagnosisTerms = [], ?int $delaySeconds = null): bool
    {
        $encounterId = (int) $encounter->id;
        if ($encounterId <= 0) {
            return false;
        }

        $delaySeconds ??= self::delaySecondsFromParams();
        $now = date('Y-m-d H:i:s');
        $scheduledAt = date('Y-m-d H:i:s', time() + max(0, $delaySeconds));
        $termsJson = $diagnosisTerms === []
            ? null
            : (string) json_encode(array_values($diagnosisTerms), JSON_UNESCAPED_UNICODE);

        $job = EncounterCodingJob::findOne(['encounter_id' => $encounterId]);
        if ($job === null) {
            $job = new EncounterCodingJob();
            $job->encounter_id = $encounterId;
            $job->diagnosis_terms_json = $termsJson;
            $job->status = EncounterCodingJob::STATUS_PENDING;
            $job->attempts = 0;
            $job->scheduled_at = $scheduledAt;
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

        $job->diagnosis_terms_json = $termsJson;
        $job->scheduled_at = $scheduledAt;
        $job->updated_at = $now;

        if ($job->status === EncounterCodingJob::STATUS_RUNNING) {
            $job->rerun_requested = 1;

            return $job->save(false, ['diagnosis_terms_json', 'scheduled_at', 'rerun_requested', 'updated_at']);
        }

        $job->status = EncounterCodingJob::STATUS_PENDING;
        $job->attempts = 0;
        $job->finished_at = null;
        $job->last_error = null;
        $job->rerun_requested = 0;

        return $job->save(false, [
            'diagnosis_terms_json',
            'scheduled_at',
            'status',
            'attempts',
            'finished_at',
            'last_error',
            'rerun_requested',
            'updated_at',
        ]);
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
            $encounter = Encounter::findOne(['id' => (int) $job->encounter_id, 'deleted_at' => null]);
            if ($encounter === null) {
                // Edición = nuevo registro: el anterior queda anulado. No se codifica.
                $job->markSkipped('Encounter anulado o inexistente: ' . $job->encounter_id);
                $skipped++;
                continue;
            }

            $annulment = self::annulmentReason($encounter);
            if ($annulment !== null) {
                $job->markSkipped('Encounter anulado: ' . $annulment);
                $skipped++;
                continue;
            }

            try {
                $context = ['diagnosis_terms' => $job->diagnosisTerms()];
                foreach (EncounterCodingTargetRegistry::all() as $target) {
                    $target->code($encounter, $context);
                }
                $job->markDone(self::delaySecondsFromParams());
                $done++;
            } catch (\Throwable $e) {
                $job->markFailed($e->getMessage());
                $failed++;
            }
        }

        return ['claimed' => count($claimed), 'done' => $done, 'failed' => $failed, 'skipped' => $skipped];
    }

    /**
     * Motivo por el que un encounter ya no debe codificarse, o null si sigue vigente.
     *
     * Una edición crea un encounter nuevo y anula el anterior (`cancelled` /
     * `entered-in-error` o soft-delete). El job del anterior se descarta.
     */
    public static function annulmentReason(Encounter $encounter): ?string
    {
        return self::annulmentReasonFor(
            (string) $encounter->status,
            $encounter->deleted_at === null ? null : (string) $encounter->deleted_at
        );
    }

    public static function annulmentReasonFor(?string $status, ?string $deletedAt): ?string
    {
        if ($deletedAt !== null) {
            return 'deleted_at';
        }

        $normalized = EncounterStatus::normalize((string) $status);
        if (in_array($normalized, [EncounterStatus::CANCELLED, EncounterStatus::ENTERED_IN_ERROR], true)) {
            return $normalized;
        }

        return null;
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
