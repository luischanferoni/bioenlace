<?php
return [
    'adminEmail' => 'admin@example.com',
    'supportEmail' => 'support@example.com',
    'senderEmail' => 'noreply@example.com',
    'senderName' => 'Example.com mailer',
    /** null o vacío: emails a archivo (runtime/mail). Producción: smtp://user:pass@host:587 */
    'mailerDsn' => null,
    'user.passwordResetTokenExpire' => 3600,
    'user.passwordMinLength' => 8,
    'bsVersion' => '5.x',
    'hostFormsAPI'=> 'http://10.10.10.235:9559',//'http://localhost:3000',//'http://saluddigital.msalsgo.gob.ar:9559'
    'SISA_APP_ID'=> '6df3d2f1',
    'SISA_APP_KEY'=>'e6d747f81e4ef3112750cc477f35fd29',
    'hf_activar_correccion' => true,
    /**
     * Push FCM de la plataforma (turnos, alertas, mensajes, etc.).
     * Proyecto Firebase separado de google_cloud_* (Vertex / voz).
     * - credentialsPath + projectId: FCM HTTP v1.
     * - fcmServerKey: alternativa legacy.
     * - httpEndpoint: proxy opcional.
     */
    'fcmPush' => [
        'projectId' => null,
        'credentialsPath' => null,
        'fcmServerKey' => null,
        'httpEndpoint' => null,
    ],
    /** Autogestión paciente: oferta de próximos slots (endpoint slots-disponibles-como-paciente en API v1). */
    /**
     * Defaults de {@see \common\models\EfectorTurnosConfig} cuando el campo en BD es NULL.
     * 0 = sin restricción por anticipación para esa operación.
     */
    'efectorTurnosConfigDefaults' => [
        'autogestion_min_horas_antes_cancelar' => 2,
        'autogestion_min_horas_antes_reprogramar' => 2,
    ],
    'turnosPaciente' => [
        /** Máximo de slots devueltos en una respuesta agrupada */
        'slots_oferta_max' => 400,
        /** Días hacia adelante que puede explorar TurnoSlotFinder */
        'slots_busqueda_max_dias' => 10,
        /** Hora límite inclusive: &lt; este HH:MM → franja `manana`, ≥ → `tarde` */
        'franja_tarde_desde' => '13:00',
        /** Tope duro si el cliente envía `limite` (anti-abuso) */
        'slots_oferta_max_cliente' => 400,
        /** Oferta de slots de hoy: solo desde ahora + N minutos (autogestión / reprogramar). */
        'slots_min_minutos_desde_ahora' => 15,
    ],
    /**
     * IDs de servicio (tabla servicios) que bajo encounter IMP listan agenda quirúrgica en home/panel (sección surgeries_day).
     * Vacío: solo heurística por nombre (cirugía, quirófano, etc.) en {@see \common\models\Servicio::esServicioAgendaQuirurgica}.
     */
    'serviciosAgendaQuirurgicaIds' => [],

    /**
     * Catálogo de acciones API: descubrimiento de controladores v1, listas filtradas por RBAC, UniversalQuery.
     * useCache => false: sin caché de aplicación en cada request (útil mientras se suman rutas/controladores).
     * Sobrescribir en params-local (frontend/common) durante construcción; en producción dejar true.
     */
    'apiActionCatalog' => [
        'useCache' => false,
    ],
    /**
     * Conectores LIS externos (FHIR pull). Credenciales en params-local.
     *
     * laboratoryConnectors.default — clave activa por defecto
     * laboratoryConnectors.connectors.<key> — class, baseUrl, clientId, clientSecret, …
     */
    'laboratoryConnectors' => [
        'default' => 'sianlabs',
        'connectors' => [
            'sianlabs' => [
                'class' => \common\components\Domain\Integrations\Laboratory\Connector\SianlabsFhirConnector::class,
                'baseUrl' => 'https://sianlabs.msalsgo.gob.ar/api/fhir/',
                'tokenUrl' => 'https://sianlabs.msalsgo.gob.ar/oauth/token',
                'clientId' => null,
                'clientSecret' => null,
            ],
        ],
    ],
    /**
     * Repositorio nacional de recetas (MSAL RDI). Sin credenciales reales usa conector `null`.
     *
     * verificationPublicBaseUrl — base pública para QR (ej. https://app.example.com/api/v1).
     * Sobrescribir en params-local.php.
     */
    'recetaDigitalRepository' => [
        'default' => 'null',
        'verificationPublicBaseUrl' => null,
        'connectors' => [
            'null' => [
                'class' => \common\components\Domain\Integrations\Prescription\Connector\NullRecetaDigitalRepositoryConnector::class,
            ],
            'msal-rdi' => [
                'class' => \common\components\Domain\Integrations\Prescription\Connector\HttpRecetaDigitalRepositoryConnector::class,
                'enabled' => false,
                'baseUrl' => null,
                'tokenUrl' => null,
                'clientId' => null,
                'clientSecret' => null,
            ],
        ],
    ],

    /**
     * Export FHIR historia clínica hacia servidor nacional / red (cola saliente).
     * Credenciales y enabled en params-local. Ver docs/plans/interoperabilidad-historia-clinica/
     */
    'clinicalHistoryExchange' => [
        'enabled' => false,
        'default' => 'null',
        'exchange_profile' => 'encounter-document-v1',
        'encounter_classes' => ['AMB', 'EMER', 'IMP'],
        'excluded_efector_ids' => [],
        'allowed_efector_ids' => null,
        'log_bundle_snapshot' => false,
        'retry' => [
            'max_attempts' => 5,
            'backoff_seconds' => [60, 300, 900, 3600, 14400],
            'batch_limit' => 20,
            'delay_after_finalize_seconds' => 120,
        ],
        'connectors' => [
            'null' => [
                'class' => \common\components\Domain\Integrations\ClinicalHistory\Connector\NullClinicalHistoryExchangeConnector::class,
            ],
            'nacional-fhir' => [
                'class' => \common\components\Domain\Integrations\ClinicalHistory\Connector\HttpNationalClinicalHistoryConnector::class,
                'enabled' => false,
                'baseUrl' => null,
                'tokenUrl' => null,
                'clientId' => null,
                'clientSecret' => null,
                'submitPath' => '/fhir/Bundle',
            ],
        ],
    ],

    /**
     * Cohortes — defaults compartidos (estructura). Activación por app en frontend/console params.
     * @see common/config/params-care-cohort.php
     */
    'care_cohort' => require __DIR__ . '/params-care-cohort.php',

    /**
     * Handlers de dominio cableados a motores genéricos (hydrators, políticas, scope, filtros, presentación).
     * Para otro rubro: copiar product-registries.php y ajustar clases.
     */
    'productRegistries' => require __DIR__ . '/product-registries.php',

    /**
     * Metadata declarativa del producto (intents, reglas NL, permisos dominio, panel home).
     * Para otro rubro: apuntar a otra carpeta bajo common/metadata/.
     * Default: @common/metadata/bioenlace (resuelto en {@see \common\components\Platform\Core\Product\ProductMetadataPaths}).
     */
    // 'productMetadataDir' => dirname(__DIR__) . '/metadata/bioenlace',
];
