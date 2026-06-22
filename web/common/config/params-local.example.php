<?php
/**
 * Copiar a params-local.php (no commitear).
 */
return [
    // Vertex / IA (proyecto integracion-voz-del-agro)
    'google_cloud_credentials_path' => __DIR__ . '/credentials/integracion-voz-del-agro-b5c802c87e77.json',
    'google_cloud_project_id' => 'integracion-voz-del-agro',
    'google_cloud_region' => 'us-central1',

    // Mail (Symfony Mailer). Vacío = guardar en runtime/mail.
    // 'mailerDsn' => 'smtp://user:pass@smtp.example.com:587',
    // 'mailerDsn' => 'sendmail://default',

    // Push FCM plataforma (proyecto Firebase august-cirrus-482714-f4)
    'fcmPush' => [
        'projectId' => 'august-cirrus-482714-f4',
        'credentialsPath' => __DIR__ . '/credentials/august-cirrus-fcm.json',
        'fcmServerKey' => null,
        'httpEndpoint' => null,
    ],

    // STT servidor (Groq Whisper) — copiar a frontend/config/params-local.php si aplica
    // 'groq_api_key' => 'gsk_...',
    // 'stt' => ['proveedor_servidor' => 'groq', 'device_enabled' => true, 'server_enabled' => true],
    // 'optimizar_audio' => false, // shared hosting sin FFmpeg

    // Export FHIR HC → nacional (cuando haya contrato)
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
    //             'statusPath' => '/fhir/Bundle/{id}/_status', // TBD polling acuse
    //         ],
    //     ],
    // ],
];
