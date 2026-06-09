<?php

/**
 * Params de consola — independientes de common/frontend/admin.
 * Repetir aquí lo que los cron necesitan (cohortes, Vertex, IAManager).
 *
 * Secretos (credenciales GCP): console/config/params-local.php
 *
 * @see web/docs/producto/asistencia-cohortes.md
 */
return [
    'ia_proveedor' => 'google',
    'vertex_ai_model' => 'gemini-2.5-flash-lite',
    'ia_usage_tracking_habilitado' => true,

    /**
     * Cola care-pack: generación sync, submit/poll Vertex, touchpoints followup.
     */
    'care_cohort' => [
        'enabled' => true,
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
    ],
];
