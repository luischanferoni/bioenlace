<?php
/**
 * Copiar a params-local.php (no commitear).
 */
return [
    // Vertex / IA (proyecto integracion-voz-del-agro)
    'google_cloud_credentials_path' => __DIR__ . '/credentials/integracion-voz-del-agro-b5c802c87e77.json',
    'google_cloud_project_id' => 'integracion-voz-del-agro',
    'google_cloud_region' => 'us-central1',

    // Packs cohorte — Vertex batch (producción)
    // 'care_cohort' => [
    //     'enabled' => true,
    //     'vertex_batch' => [
    //         'enabled' => true,
    //         'gcs_bucket' => 'bioenlace-care-batch-prod',
    //         'min_jobs_for_vertex' => 10,
    //         'max_wait_minutes' => 120,
    //     ],
    // ],

    // Push FCM plataforma (proyecto Firebase august-cirrus-482714-f4)
    'fcmPush' => [
        'projectId' => 'august-cirrus-482714-f4',
        'credentialsPath' => __DIR__ . '/credentials/august-cirrus-fcm.json',
        'fcmServerKey' => null,
        'httpEndpoint' => null,
    ],
];
