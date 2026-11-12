<?php

namespace common\models\Clinical;

use yii\db\ActiveRecord;

/**
 * Cola de codificación clínica de un encounter.
 *
 * @property int $id
 * @property int $encounter_id
 * @property string|null $diagnosis_terms_json
 * @property string $status
 * @property int $attempts
 * @property int $rerun_requested
 * @property string|null $last_error
 * @property string $scheduled_at
 * @property string|null $started_at
 * @property string|null $finished_at
 * @property string $created_at
 * @property string|null $updated_at
 */
class EncounterCodingJob extends ActiveRecord
{
    public const STATUS_PENDING = 'PENDING';
    public const STATUS_RUNNING = 'RUNNING';
    public const STATUS_DONE = 'DONE';
    public const STATUS_FAILED = 'FAILED';
    public const STATUS_SKIPPED = 'SKIPPED';

    public const MAX_ATTEMPTS = 5;

    public static function tableName(): string
    {
        return '{{%encounter_coding_job}}';
    }

    /**
     * Backoff exponencial acotado a 1 hora: 60s, 120s, 240s, 480s, 960s.
     */
    public static function backoffSeconds(int $attempts): int
    {
        return min(3600, 60 * (2 ** max(0, $attempts - 1)));
    }

    /**
     * @return list<string>
     */
    public function diagnosisTerms(): array
    {
        return self::parseDiagnosisTerms($this->diagnosis_terms_json);
    }

    /**
     * @return list<string>
     */
    public static function parseDiagnosisTerms(?string $json): array
    {
        $data = json_decode((string) $json, true);
        if (!is_array($data)) {
            return [];
        }

        $terms = [];
        foreach ($data as $term) {
            $term = trim((string) $term);
            if ($term !== '' && !in_array($term, $terms, true)) {
                $terms[] = $term;
            }
        }

        return $terms;
    }

    public function markDone(?int $rerunDelaySeconds = null): void
    {
        $now = date('Y-m-d H:i:s');
        $this->finished_at = $now;
        $this->updated_at = $now;
        $this->diagnosis_terms_json = null;
        $this->last_error = null;

        if ((int) $this->rerun_requested === 1) {
            $this->status = self::STATUS_PENDING;
            $this->attempts = 0;
            $this->rerun_requested = 0;
            $this->scheduled_at = date('Y-m-d H:i:s', time() + max(0, (int) $rerunDelaySeconds));
        } else {
            $this->status = self::STATUS_DONE;
        }

        $this->save(false, [
            'status',
            'attempts',
            'rerun_requested',
            'scheduled_at',
            'finished_at',
            'updated_at',
            'diagnosis_terms_json',
            'last_error',
        ]);
    }

    public function markFailed(string $error): void
    {
        $now = date('Y-m-d H:i:s');
        $this->last_error = mb_substr($error, 0, 2000);
        if ($this->attempts >= self::MAX_ATTEMPTS) {
            $this->status = self::STATUS_FAILED;
            $this->finished_at = $now;
        } else {
            $this->status = self::STATUS_PENDING;
            $this->scheduled_at = date('Y-m-d H:i:s', time() + self::backoffSeconds($this->attempts));
        }
        $this->updated_at = $now;
        $this->save(false, ['status', 'finished_at', 'scheduled_at', 'updated_at', 'last_error']);
    }

    /**
     * Encounter anulado: no se codifica y no se reintenta.
     */
    public function markSkipped(string $reason): void
    {
        $now = date('Y-m-d H:i:s');
        $this->status = self::STATUS_SKIPPED;
        $this->finished_at = $now;
        $this->updated_at = $now;
        $this->diagnosis_terms_json = null;
        $this->last_error = mb_substr($reason, 0, 2000);
        $this->save(false, ['status', 'finished_at', 'updated_at', 'diagnosis_terms_json', 'last_error']);
    }
}
