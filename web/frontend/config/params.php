<?php

return [
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
    
    // Optimizaciones de procesamiento
    'comprimir_datos_transito' => true, // Comprimir datos con gzip en tránsito
    'usar_cpu_tareas_simples' => true, // Usar CPU para tareas simples (sin GPU)
    'max_modelos_memoria' => 3, // Máximo de modelos cargados simultáneamente en memoria
    'chunk_audio_duration' => 10, // Duración de chunks de audio en segundos
    'similitud_minima_respuestas' => 0.85, // Umbral mínimo de similitud para reutilizar respuestas predefinidas
    'optimizar_audio' => true, // Activar optimizaciones de audio (compresión, eliminación de silencios)
    'ffmpeg_path' => 'ffmpeg', // Ruta al ejecutable de FFmpeg
    
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
