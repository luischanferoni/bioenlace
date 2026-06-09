<?php
/**
 * Copiar a params-local.php (no commitear).
 */
return [
    // Vertex / IA (proyecto integracion-voz-del-agro)
    'google_cloud_credentials_path' => __DIR__ . '/credentials/integracion-voz-del-agro-b5c802c87e77.json',
    'google_cloud_project_id' => 'integracion-voz-del-agro',
    'google_cloud_region' => 'us-central1',

    /**
     * Vertex batch cohortes (prod): solo frontend/admin si aplica.
     * Cron / Vertex: console/config/params-local.php
     */

    // Push FCM plataforma (proyecto Firebase august-cirrus-482714-f4)
    'fcmPush' => [
        'projectId' => 'august-cirrus-482714-f4',
        'credentialsPath' => __DIR__ . '/credentials/august-cirrus-fcm.json',
        'fcmServerKey' => null,
        'httpEndpoint' => null,
    ],
];
