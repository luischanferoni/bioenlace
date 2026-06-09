<?php
/**
 * Overrides consola (servidor). Copiar a params-local.php (no commitear).
 *
 * BD: console/config/main-local.php
 */
return [
    'google_cloud_credentials_path' => __DIR__ . '/../../common/config/credentials/integracion-voz-del-agro-b5c802c87e77.json',
    'google_cloud_project_id' => 'integracion-voz-del-agro',
    'google_cloud_region' => 'us-central1',

    // Vertex batch cohortes (prod)
    // 'care_cohort' => [
    //     'vertex_batch' => [
    //         'enabled' => true,
    //         'gcs_bucket' => 'bioenlace-care-batch-prod',
    //     ],
    // ],
];
