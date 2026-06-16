<?php

/**
 * Packs de cohorte (asistencia, seguimiento, educación).
 *
 * Definición compartida (common). Cada aplicación activa o extiende en su params:
 * - frontend/config/params.php — API / asistente / móvil (enabled true)
 * - console/config/params.php — cron care-pack (enabled true)
 * - admin: hereda common; queda disabled salvo override explícito
 *
 * Secretos prod (bucket GCS): common/config/params-local.php o console/frontend params-local.
 *
 * @see web/docs/producto/asistencia-cohortes.md
 */
return [
    'enabled' => false,
    'pack_ttl_days' => 30,
    'generation_delay_minutes' => 0,
    'vertex_batch' => [
        'enabled' => false,
        'gcs_bucket' => '',
        'gcs_input_prefix' => 'care-batch/input/',
        'gcs_output_prefix' => 'care-batch/output/',
        'min_jobs_for_vertex' => 10,
        'max_wait_minutes' => 120,
    ],
];
