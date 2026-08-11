<?php

return [
    /** Versión de la SPA web (headers X-App-Version hacia /api/v1/* para compatibilidad de UI) */
    'spaWebAppVersion' => '1.0.0',

    'path' => '/frontend',
    'botonera' => ['view' => false, 'params' => []], // para guardar el path de un partial en donde esten los botones
    
    // Configuración de IA    
    'ia_proveedor' => 'google', // 'huggingface', 'groq', 'openai', 'ollama', 'google' (Vertex AI)
    
    // Configuración de modelos HuggingFace optimizados
    'hf_model_text_gen' => 'deepseek-ai/DeepSeek-R1:hyperbolic', // Modelo para generación de texto (DeepSeek R1)
    'hf_model_correction' => 'deepseek-ai/DeepSeek-R1:hyperbolic', // Modelo para corrección
    'hf_model_analysis' => 'deepseek-ai/DeepSeek-R1:hyperbolic', // Modelo para análisis (DeepSeek R1)
    'hf_embedding_model' => 'sentence-transformers/paraphrase-multilingual-MiniLM-L12-v2', // Modelo de embeddings
    'hf_stt_model' => 'jonatasgrosman/wav2vec2-xlsr-53-spanish', // Modelo STT alternativo si se selecciona Hugging Face explícitamente
    
    // Parámetros de optimización de costos
    'hf_max_length' => 1000, // Longitud máxima de respuesta (aumentado para DeepSeek R1)
    'hf_temperature' => 0.3, // Temperature para análisis médico con DeepSeek R1
    'ia_cache_ttl' => 604800, // TTL de cache para respuestas de IA (7 días) - Optimizado para reducir costos
    'correccion_cache_ttl' => 604800, // TTL de cache para correcciones (7 días) - Optimizado para reducir costos
    
    // Control de cache para pruebas (desactivar para forzar llamadas a IA)
    'ia_cache_desactivado' => true, // true = desactiva cache de estructuración/análisis (fuerza llamadas a IA)
    'correccion_cache_desactivado' => true, // true = desactiva cache de corrección (fuerza llamadas a IA)

    /** Modelo Gemini en Vertex / Generative Language API (producción: gemini-2.5-flash-lite). */
    'vertex_ai_model' => 'gemini-2.5-flash-lite',
    'vertex_ai_location' => 'us-central1',
    'vertex_ai_temperature' => 0.3,
    'vertex_ai_max_tokens' => 1000,
    'google_max_output_tokens' => 8192,

    /**
     * Acumula usageMetadata de Gemini en AICostTracker (tokens, cachedContentTokenCount por contexto).
     * Activar en staging para calibrar columnas de costos-api.md; ver web/docs/costos/pruebas-costos-ia.md.
     */
    'ia_usage_tracking_habilitado' => true,

    /**
     * Simula cachedContents en local: systemInstruction estable + user variable, registro en memoria
     * y estimación de cachedContentTokenCount si la API no devuelve hits aún.
     */
    'vertex_context_cache_simulado' => true,

    /** Ventana de historial para ConversationalChannel (coste y contexto acotados). */
    'asistente_conversacional_historial_max_turnos' => 5,
    'asistente_conversacional_historial_max_chars' => 3200,

    /**
     * Bloque clínico acotado en prompts IA (captura, motivos batch, chat conversacional).
     * max_chars ≈ 600 tokens; perfiles limitan ítems por sección.
     */
    'patient_ai_context' => [
        'max_chars' => 2400,
        'profiles' => [
            'encounter' => ['max_conditions' => 8, 'max_medications' => 8, 'max_allergies' => 12],
            'motivos' => ['max_conditions' => 6, 'max_medications' => 6, 'max_allergies' => 12],
            'conversational' => ['max_conditions' => 4, 'max_medications' => 4, 'max_allergies' => 8],
        ],
    ],

    /**
     * Cohortes: activo en API / asistente / móvil (extiende common/config/params-care-cohort.php).
     * @see web/docs/producto/asistencia-cohortes.md
     */
    'care_cohort' => array_replace_recursive(
        require __DIR__ . '/../../common/config/params-care-cohort.php',
        [
            'enabled' => true,
        ]
    ),

    // Optimizaciones de procesamiento
    'comprimir_datos_transito' => true, // Comprimir datos con gzip en tránsito
    'usar_cpu_tareas_simples' => true, // Usar CPU para tareas simples (sin GPU)
    'max_modelos_memoria' => 3, // Máximo de modelos cargados simultáneamente en memoria
    'chunk_audio_duration' => 10, // Duración de chunks de audio en segundos
    'similitud_minima_respuestas' => 0.85, // Umbral mínimo de similitud para reutilizar respuestas predefinidas
    'optimizar_audio' => false, // Activar optimizaciones de audio (compresión, eliminación de silencios)
    'ffmpeg_path' => 'ffmpeg', // Ruta al ejecutable de FFmpeg

    /**
     * STT servidor (Groq / Hugging Face) y política device vs cloud.
     * Ver web/docs/costos/estrategias-reduccion/stt.md
     */
    'stt' => [
        /** groq | huggingface */
        'proveedor_servidor' => 'groq',
        'device_enabled' => true,
        'server_enabled' => true,
        'groq_model' => 'whisper-large-v3-turbo',
        'groq_language' => 'es',
    ],

    /**
     * STT en dispositivo (captura clínica): umbrales de calidad y fallback a servidor.
     * `enabled` false = solo texto manual o STT servidor (si server_enabled).
     */
    'stt_device' => [
        'enabled' => true,
        'min_confidence' => 0.75,
        'min_chars' => 3,
        'min_words_per_minute' => 20,
        'max_filler_ratio' => 0.7,
        'max_non_alpha_ratio' => 0.5,
        'max_client_edit_ratio' => 0.35,
        'profiles' => [
            'captura_clinica' => [
                'min_confidence' => 0.85,
            ],
            'motivos_consulta' => [
                'min_confidence' => 0.75,
            ],
        ],
    ],

    /** Minutos antes del turno en que se cierra el chat de motivos y corre el lote IA (cron turno-notificacion). */
    'motivos_consulta_cierre_minutos' => 10,
    /** Minutos antes del turno en que abre «Preparar tu consulta» (motivos, intake, pre-consulta). Default 240 = 4 h. */
    'encounter_journey_preparar_minutos_antes' => 240,
    /** Minutos antes del turno en que el médico puede abrir historia clínica (motivos resumidos por IA). */
    'historia_clinica_apertura_medico_minutos' => 30,

    /**
     * Login de revisión Play / App Store (cuentas allowlisted en params).
     * Habilitar solo en el entorno que revisan las tiendas. Credenciales en params-local.
     *
     * @see mobile/PLAY_APP_ACCESS.md
     */
    'play_review_login_habilitado' => false,
    'play_review_accounts' => [
        // Definir en params-local.php, por ejemplo:
        // ['username' => 'play_review_paciente'],
        // ['username' => 'medico_med_general_863'],
    ],

    /**
     * Acceso demo sandbox desde el sitio institucional (código de un solo uso → /site/demo-entrar).
     * Habilitar en staging/prod controlado; mode ephemeral provisiona médico + seed por visita.
     *
     * @see web/docs/plans/demo-sandbox-institucional/design.md
     */
    'demo_sandbox_habilitado' => true,
    'demo_sandbox' => [
        /** TTL del código de un solo uso (institucional → app). */
        'ttl_seconds' => 900,
        /** TTL de la sesión demo efímera (médico + seed); purga por cron/logout. */
        'session_ttl_seconds' => 14400,
        'max_per_ip_hour' => 10,
        /** Captcha challenge en cache (sin sesión PHP; apto cross-origin institucional → API). */
        'require_captcha' => true,
        'captcha_ttl_seconds' => 300,
        'captcha_length' => 4,
        /** Captcha en POST demo-acceso-mobile (app Personal de Salud). Default off. */
        'require_captcha_mobile' => false,
        /**
         * Plantilla del sandbox: SOLO por efector_codigo_sisa DEV (default DEV99002PRIV).
         * id_efector numérico se ignora si no es esa plantilla DEV (nunca usar 863 u otro centro real).
         */
        'efector_codigo_sisa' => 'DEV99002PRIV',
        'id_efector' => 0,
        'servicio_nombre' => 'MED GENERAL',
        'seed' => [
            'pacientes' => 6,
            'turnos' => 2,
            'with_agenda' => true,
            /** Encounter AMB in-progress sobre el 1.er turno (captura clínica). */
            'with_consulta_amb' => true,
            /** Consultas clínicas por mensaje (encounter VR planned → bandeja Virtual). */
            'with_consulta_async' => true,
            'consultas_async' => 2,
            /** Best-effort: no exige entitlement EMER para persistir la fila. */
            'with_guardia' => true,
            /** Crea piso/sala/cama efímeros + ingreso (sin assert HTTP). */
            'with_internacion' => true,
        ],
        /** Base absoluta de la app (sin barra final). Vacío = createAbsoluteUrl Yii. En prod: https://app.bioenlace.io */
        'app_base_url' => '',
        /**
         * Perfiles CTA. mode=ephemeral crea PES+seed en POST demo-acceso (issue).
         * mode=shared_account reutiliza username (solo paciente; staff nunca usa legacy).
         */
        'servicio_enfermeria_nombre' => 'ENFERMERIA',
        'profiles' => [
            'staff' => [
                'label' => 'Médico demo (captura y turnos)',
                'mode' => 'ephemeral',
            ],
            'enfermeria' => [
                'label' => 'Enfermería demo (guardia e internación)',
                'mode' => 'ephemeral',
            ],
            'administrativo' => [
                'label' => 'Administrativo demo (ingreso a guardia)',
                'mode' => 'ephemeral',
            ],
            // 'paciente' => [
            //     'label' => 'Paciente demo',
            //     'mode' => 'shared_account',
            //     'username' => 'play_review_paciente',
            // ],
        ],
    ],

    // Configuración de JWT para autenticación API
    'jwtSecret' => 'yt14zxFvJUdIXnOIHP87TpfR42JKyi6Ni2wUX5JoHpLiLtikL1p7vdHWcvGIpCfK', // App HS256 + MPI HS512 (MpiJwtTokenService)
];
