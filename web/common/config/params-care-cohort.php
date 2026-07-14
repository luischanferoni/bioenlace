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
    /**
     * Follow-up post-consulta: mínimos y defaults si el pack IA trae pocos touchpoints.
     * delay_days de control puede sobrescribirse con ServiceRequest.reminder_json.delay_days.
     */
    'followup' => [
        'min_touchpoints' => 2,
        'default_touchpoints' => [
            [
                'delay_days' => 3,
                'title' => 'Cómo seguís',
                'purpose' => 'evolution',
                'form_kind' => 'evolution_short',
            ],
            [
                'delay_days' => 15,
                'title' => 'Control de evolución',
                'purpose' => 'evolution',
                'form_kind' => 'symptoms',
            ],
        ],
    ],
    'vertex_batch' => [
        'enabled' => false,
        'gcs_bucket' => '',
        'gcs_input_prefix' => 'care-batch/input/',
        'gcs_output_prefix' => 'care-batch/output/',
        'min_jobs_for_vertex' => 10,
        'max_wait_minutes' => 120,
    ],
];
