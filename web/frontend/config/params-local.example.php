<?php
/**
 * Overrides solo web/API (asistente, captura clínica, STT, tuning IA).
 *
 * Copiar a params-local.php (no commitear).
 * Secretos (API keys, GCP JSON, OAuth integraciones): common/config/params-local.php
 *
 * Merge:
 *   common/params.php → common/params-local.php → frontend/params.php → ESTE
 */
return [
    // --- Proveedor IA (default en frontend/params.php: google) ---
    // 'ia_proveedor' => 'groq', // huggingface | groq | openai | ollama | google

    // --- Vertex / Gemini (tuning; credenciales en common/params-local.php) ---
    'vertex_ai_model' => 'gemini-2.5-flash-lite',
    'vertex_ai_location' => 'us-central1',
    'vertex_ai_temperature' => 0.3,
    'vertex_ai_max_tokens' => 1000,
    'google_max_output_tokens' => 8192,

    /** Acumular usageMetadata en AICostTracker (staging/calibración costos). */
    'ia_usage_tracking_habilitado' => true,

    // --- Desarrollo: forzar llamadas a IA sin cache ---
    // 'ia_cache_desactivado' => true,
    // 'correccion_cache_desactivado' => true,
    // 'vertex_context_cache_simulado' => true,

    // --- Audio / STT (ver web/docs/costos/estrategias-reduccion/stt.md) ---
    /** false en shared hosting sin FFmpeg; true en VPS con ffmpeg instalado */
    'optimizar_audio' => false,
    // 'ffmpeg_path' => 'ffmpeg',

    /**
     * STT: device (Web Speech / nativo) vs servidor (Groq/HF).
     * groq_api_key / hf_api_key en common/params-local.php
     */
    'stt' => [
        'proveedor_servidor' => 'groq', // groq | huggingface
        'device_enabled' => true,
        'server_enabled' => false,    // true = fallback cloud cuando falla device
        'groq_model' => 'whisper-large-v3-turbo',
        'groq_language' => 'es',
    ],

    // --- JWT API (solo si distinto del default de frontend/params.php) ---
    // 'jwtSecret' => '…',

    // --- Sistema híbrido corrección clínica (si se reactiva en params.php) ---
    // 'hf_modelo_clinico' => 'PlanTL-GOB-ES/roberta-base-biomedical-clinical-es',
    // 'sistema_hibrido' => [ … ],
];
