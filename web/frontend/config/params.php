<?php

return [
    'adminEmail' => 'admin@example.com',
    'path' => '/frontend',
    'bsVersion' => '5.x',
    'vaCartelPaciente' => true,  // Para mostrar el cartel de que se esta trabajando con cierto paciente
    'botonera' => ['view' => false, 'params' => []], // para guardar el path de un partial en donde esten los botones
    
    // Configuración de IA    
    'ia_proveedor' => 'huggingface', // 'huggingface', 'groq', 'openai' , ollama
    
    // NOTA: HuggingFace ofrece tier gratuito con 30,000 requests/mes gratis
    // Optimizar uso para maximizar requests gratuitos antes de usar tier de pago
    'hf_use_free_tier' => true, // Priorizar uso del tier gratuito (30K requests/mes)
    
    // Configuración de modelos HuggingFace optimizados
    'hf_model_text_gen' => 'deepseek-ai/DeepSeek-R1', // Modelo para generación de texto (DeepSeek R1)
    'hf_model_correction' => 'PlanTL-GOB-ES/roberta-base-biomedical-clinical-es', // Modelo para corrección
    'hf_model_analysis' => 'deepseek-ai/DeepSeek-R1', // Modelo para análisis (DeepSeek R1)
    'hf_embedding_model' => 'sentence-transformers/paraphrase-multilingual-MiniLM-L12-v2', // Modelo de embeddings
    'hf_stt_model' => 'jonatasgrosman/wav2vec2-xlsr-53-spanish', // Modelo Speech-to-Text (economico por defecto)
    
    // Parámetros de optimización de costos
    'hf_max_length' => 1000, // Longitud máxima de respuesta (aumentado para DeepSeek R1)
    'hf_temperature' => 0.3, // Temperature para análisis médico con DeepSeek R1
    'ia_cache_ttl' => 604800, // TTL de cache para respuestas de IA (7 días) - Optimizado para reducir costos
    'correccion_cache_ttl' => 604800, // TTL de cache para correcciones (7 días) - Optimizado para reducir costos
    'embedding_cache_ttl' => 2592000, // TTL de cache para embeddings (30 días) - Optimizado para reducir costos
    'stt_cache_ttl' => 2592000, // TTL de cache para transcripciones STT (30 días) - Optimizado para reducir costos
    
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
    'google_vision_project_id' => '', // Project ID (opcional)
    'azure_face_api_key' => '', // API key para Azure Face API
    'azure_face_endpoint' => '', // Endpoint de Azure Face API (ej: https://<resource>.cognitiveservices.azure.com)
    'azure_face_min_quality' => 0.35, // Umbral mínimo de qualityForRecognition (0.0 - 1.0)
    'azure_face_fail_on_occlusion' => true, // Rechazar si hay oclusiones (ojos/boca)
    'face_match_threshold' => 0.7, // Umbral de similitud (0.0 - 1.0)
    
    // Configuración de JWT para autenticación API
    'jwtSecret' => 'yt14zxFvJUdIXnOIHP87TpfR42JKyi6Ni2wUX5JoHpLiLtikL1p7vdHWcvGIpCfK', // Misma clave que el componente jwt
];
