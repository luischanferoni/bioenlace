<?php
/**
 * Secretos e integraciones de plataforma (compartidos por web y admin).
 *
 * Copiar a params-local.php (no commitear).
 * JSON de credenciales: common/config/credentials/ (ver .gitignore del directorio).
 *
 * Merge web/admin:
 *   common/params.php → ESTE → {frontend|admin}/params.php → {frontend|admin}/params-local.php
 *
 * Consola: common/params.php → ESTE → console/params.php → console/params-local.php
 */
return [
    // --- API keys (IA, embeddings, STT servidor Groq/HF) ---
    'groq_api_key' => 'gsk_...',       // https://console.groq.com/
    'openai_api_key' => 'sk-...',      // https://platform.openai.com/ (embeddings fallback)
    'hf_api_key' => 'hf_...',          // https://huggingface.co/settings/tokens

    // --- Vertex / Google Cloud (proyecto integracion-voz-del-agro). Ver GOOGLE_CLOUD_SETUP.md ---
    'google_cloud_credentials_path' => __DIR__ . '/credentials/integracion-voz-del-agro-b5c802c87e77.json',
    'google_cloud_project_id' => 'integracion-voz-del-agro',
    'google_cloud_region' => 'us-central1',
    // 'google_cloud_api_key' => '', // solo dev local; menos seguro que500 JSON

    // --- Mail (Symfony Mailer). null/vacío = archivo en runtime/mail ---
    // 'mailerDsn' => 'smtp://user:pass@smtp.example.com:587',
    // 'mailerDsn' => 'sendmail://default',

    // --- Didit — identidad (registro) y auth biométrica (POST /api/v1/registro/registrar, login-biometrico) ---
    // La app móvil obtiene el workflow vía GET /api/v1/registro/config-movil (este valor).
    'didit_api_key' => '…', // Didit Console → API Keys
    // UUID workflow KYC paciente (Didit Console → Workflows). Requerido para registro admin con foto y app móvil.
    'didit_paciente_kyc_workflow_id' => '…',
    // Opcional pero recomendado: workflow solo Biometric Authentication (selfie + face match)
    // para reingreso tras cerrar sesión en app paciente. Si se omite, config-movil devuelve el KYC.
    // 'didit_paciente_biometric_workflow_id' => '…',

    // --- WhatsApp Cloud API (asistente paciente; webhook GET/POST /api/v1/whatsapp/webhook) ---
    // 'whatsapp' => [
    //     'phoneNumberId' => '…',
    //     'accessToken' => '…',
    //     'verifyToken' => '…', // el mismo que configures en Meta
    //     'appSecret' => '…',   // App Secret para X-Hub-Signature-256
    //     'apiVersion' => 'v21.0',
    //     'appDeepLinkBase' => 'https://app.bioenlace.io/',
    // ],

    // --- Push FCM plataforma (proyecto Firebase distinto de Vertex) ---
    'fcmPush' => [
        'projectId' => 'august-cirrus-482714-f4',
        'credentialsPath' => __DIR__ . '/credentials/august-cirrus-fcm.json',
        'fcmServerKey' => null,
        'httpEndpoint' => null,
    ],

    // --- Receta digital nacional (MSAL RDI) ---
    'recetaDigitalRepository' => [
        'verificationPublicBaseUrl' => 'https://app.bioenlace.io/api/v1',
        // 'default' => 'msal-rdi',
        // 'connectors' => [
        //     'msal-rdi' => [
        //         'enabled' => true,
        //         'baseUrl' => 'https://…',
        //         'tokenUrl' => 'https://…/oauth/token',
        //         'clientId' => '…',
        //         'clientSecret' => '…',
        //     ],
        // ],
    ],

    // --- LIS externos (FHIR pull) ---
    // 'laboratoryConnectors' => [
    //     'connectors' => [
    //         'sianlabs' => [
    //             'clientId' => '…',
    //             'clientSecret' => '…',
    //         ],
    //     ],
    // ],

    // --- Export FHIR historia clínica → servidor nacional ---
    // Encolar al finalizar encounter: web lee ESTE archivo.
    // Cron process-outbound: repetir el mismo bloque en console/config/params-local.php
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
