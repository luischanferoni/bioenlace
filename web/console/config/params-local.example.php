<?php
/**
 * Secretos y overrides para consola/cron (yii …).
 *
 * Copiar a params-local.php (no commitear).
 * La consola también mergea common/config/params-local.php (secretos compartidos).
 * Repetir en ESTE archivo solo overrides exclusivos de cron.
 *
 * BD y componentes: console/config/main-local.php
 *
 * @see web/docs/producto/asistencia-cohortes.md
 */
return [
    // --- Vertex / Google Cloud (cohortes batch, IAManager en cron) ---
    'google_cloud_credentials_path' => __DIR__ . '/../../common/config/credentials/integracion-voz-del-agro-b5c802c87e77.json',
    'google_cloud_project_id' => 'integracion-voz-del-agro',
    'google_cloud_region' => 'us-central1',

    // --- IA en cron (defaults en console/params.php) ---
    // 'ia_proveedor' => 'google',
    // 'vertex_ai_model' => 'gemini-2.5-flash-lite',
    // 'ia_usage_tracking_habilitado' => true,

    // --- API keys solo si algún comando las usa directamente ---
    // 'groq_api_key' => 'gsk_...',
    // 'hf_api_key' => 'hf_...',
    // 'openai_api_key' => 'sk-...',

    // --- Cohortes: Vertex Batch (producción) ---
    // 'care_cohort' => [
    //     'vertex_batch' => [
    //         'enabled' => true,
    //         'gcs_bucket' => 'bioenlace-care-batch-prod',
    //     ],
    // ],

    // --- Export FHIR HC (cron: php yii clinical-history-exchange/process-outbound) ---
    // Mismo bloque que en common/config/params-local.php
    // 'clinicalHistoryExchange' => [
    //     'enabled' => true,
    //     'default' => 'nacional-fhir',
    //     'log_bundle_snapshot' => true,
    //     'connectors' => [
    //         'nacional-fhir' => [
    //             'enabled' => true,
    //             'baseUrl' => 'https://…',
    //             'tokenUrl' => 'https://…/oauth/token',
    //             'clientId' => '…',
    //             'clientSecret' => '…',
    //             'submitPath' => '/fhir/Bundle', // TBD contrato estatal
    //         ],
    //     ],
    // ],
];
