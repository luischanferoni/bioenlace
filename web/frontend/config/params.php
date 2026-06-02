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
    'hf_stt_model' => 'jonatasgrosman/wav2vec2-xlsr-53-spanish', // Modelo Speech-to-Text (economico por defecto)
    
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
    
    // Optimizaciones de procesamiento
    'comprimir_datos_transito' => true, // Comprimir datos con gzip en tránsito
    'usar_cpu_tareas_simples' => true, // Usar CPU para tareas simples (sin GPU)
    'max_modelos_memoria' => 3, // Máximo de modelos cargados simultáneamente en memoria
    'chunk_audio_duration' => 10, // Duración de chunks de audio en segundos
    'similitud_minima_respuestas' => 0.85, // Umbral mínimo de similitud para reutilizar respuestas predefinidas
    'optimizar_audio' => true, // Activar optimizaciones de audio (compresión, eliminación de silencios)
    'ffmpeg_path' => 'ffmpeg', // Ruta al ejecutable de FFmpeg

    /**
     * STT en dispositivo (captura clínica): umbrales de calidad y fallback a servidor.
     * Ver web/docs/costos/estrategias-reduccion/stt.md
     */
    'stt_device' => [
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
        ],
    ],

    /** Minutos antes del turno en que se cierra el chat de motivos y corre el lote IA (cron turno-notificacion). */
    'motivos_consulta_cierre_minutos' => 2,
    /** Minutos antes del turno en que el médico puede abrir historia clínica (motivos resumidos por IA). */
    'historia_clinica_apertura_medico_minutos' => 1,
    
    // Configuración de reconocimiento facial
    'face_verification_provider' => 'azure', // 'azure', 'google', 'simple'
    'google_vision_api_key' => '', // API key para Google Vision API
    
    // Google Cloud / Vertex AI / API keys: ver frontend/config/params-local.php
    'azure_face_api_key' => '', // API key para Azure Face API
    'azure_face_endpoint' => '', // Endpoint de Azure Face API (ej: https://<resource>.cognitiveservices.azure.com)
    'azure_face_min_quality' => 0.35, // Umbral mínimo de qualityForRecognition (0.0 - 1.0)
    'azure_face_fail_on_occlusion' => true, // Rechazar si hay oclusiones (ojos/boca)
    'face_match_threshold' => 0.7, // Umbral de similitud (0.0 - 1.0)
    
    // Configuración de JWT para autenticación API
    'jwtSecret' => 'yt14zxFvJUdIXnOIHP87TpfR42JKyi6Ni2wUX5JoHpLiLtikL1p7vdHWcvGIpCfK', // Misma clave que el componente jwt
];
