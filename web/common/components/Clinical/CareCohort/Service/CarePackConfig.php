<?php

namespace common\components\Clinical\CareCohort\Service;

use Yii;

final class CarePackConfig
{
    public static function isEnabled(): bool
    {
        return (bool) (Yii::$app->params['care_cohort']['enabled'] ?? false);
    }

    public static function packTtlDays(): int
    {
        return max(1, (int) (Yii::$app->params['care_cohort']['pack_ttl_days'] ?? 30));
    }

    public static function generationDelayMinutes(): int
    {
        return max(0, (int) (Yii::$app->params['care_cohort']['generation_delay_minutes'] ?? 0));
    }

    public static function vertexBatchEnabled(): bool
    {
        $cfg = Yii::$app->params['care_cohort']['vertex_batch'] ?? [];

        return self::isEnabled()
            && (bool) ($cfg['enabled'] ?? false)
            && trim((string) ($cfg['gcs_bucket'] ?? '')) !== '';
    }

    /**
     * @return array<string, mixed>
     */
    public static function vertexBatch(): array
    {
        return Yii::$app->params['care_cohort']['vertex_batch'] ?? [];
    }

    public static function minJobsForVertex(): int
    {
        return max(1, (int) (self::vertexBatch()['min_jobs_for_vertex'] ?? 10));
    }
}
