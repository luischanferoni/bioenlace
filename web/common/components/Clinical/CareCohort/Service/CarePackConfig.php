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

    /**
     * Tras este tiempo, enviar lote aunque haya menos jobs que min_jobs_for_vertex (0 = desactivado).
     */
    public static function maxWaitMinutesForVertex(): int
    {
        return max(0, (int) (self::vertexBatch()['max_wait_minutes'] ?? 120));
    }

    public static function gcsBucket(): string
    {
        return trim((string) (self::vertexBatch()['gcs_bucket'] ?? ''));
    }

    /**
     * @return array{ok: bool, errors: list<string>, warnings: list<string>, config: array<string, mixed>}
     */
    public static function vertexBatchReadiness(): array
    {
        $errors = [];
        $warnings = [];
        $cfg = self::vertexBatch();
        $config = [
            'care_cohort_enabled' => self::isEnabled(),
            'vertex_batch_enabled_flag' => (bool) ($cfg['enabled'] ?? false),
            'vertex_batch_active' => self::vertexBatchEnabled(),
            'gcs_bucket' => self::gcsBucket(),
            'min_jobs_for_vertex' => self::minJobsForVertex(),
            'max_wait_minutes' => self::maxWaitMinutesForVertex(),
            'gcs_input_prefix' => (string) ($cfg['gcs_input_prefix'] ?? 'care-batch/input/'),
            'gcs_output_prefix' => (string) ($cfg['gcs_output_prefix'] ?? 'care-batch/output/'),
        ];

        if (!self::isEnabled()) {
            $warnings[] = 'care_cohort.enabled está en false.';
        }
        if (!(bool) ($cfg['enabled'] ?? false)) {
            $warnings[] = 'care_cohort.vertex_batch.enabled está en false.';
        }
        if (self::gcsBucket() === '') {
            $errors[] = 'Falta care_cohort.vertex_batch.gcs_bucket.';
        }
        $projectId = trim((string) (Yii::$app->params['google_cloud_project_id'] ?? ''));
        if ($projectId === '') {
            $errors[] = 'Falta google_cloud_project_id.';
        }
        $creds = trim((string) (Yii::$app->params['google_cloud_credentials_path'] ?? ''));
        if ($creds === '' || !is_file($creds)) {
            $errors[] = 'google_cloud_credentials_path inválido o inexistente.';
        }

        return [
            'ok' => $errors === [] && self::vertexBatchEnabled(),
            'errors' => $errors,
            'warnings' => $warnings,
            'config' => $config,
        ];
    }
}
