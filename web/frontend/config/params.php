<?php

return [
    'adminEmail' => 'admin@example.com',
    'path' => '/frontend',
    'bsVersion' => '5.x',
    'vaCartelPaciente' => true,  // Para mostrar el cartel de que se esta trabajando con cierto paciente
    'botonera' => ['view' => false, 'params' => []], // para guardar el path de un partial en donde esten los botones
    
    // Configuración de IA
    // NOTA: 'ollama' no está disponible (requiere infraestructura/hardware local)
    'ia_proveedor' => 'ollama', // 'huggingface', 'groq', 'openai' (ollama no disponible)
    'groq_api_key' => '', // API key para Groq
    'openai_api_key' => '', // API key para OpenAI
    'hf_api_key' => '', // API key para Hugging Face
    // NOTA: HuggingFace ofrece tier gratuito con 30,000 requests/mes gratis
    // Optimizar uso para maximizar requests gratuitos antes de usar tier de pago
    'hf_use_free_tier' => true, // Priorizar uso del tier gratuito (30K requests/mes)
    
    // Configuración de modelos HuggingFace optimizados
    'hf_model_text_gen' => 'HuggingFaceH4/zephyr-7b-beta', // Modelo para generación de texto
    'hf_model_correction' => 'PlanTL-GOB-ES/roberta-base-biomedical-clinical-es', // Modelo para corrección
    'hf_model_analysis' => 'microsoft/DialoGPT-small', // Modelo para análisis
    'hf_embedding_model' => 'sentence-transformers/paraphrase-multilingual-MiniLM-L12-v2', // Modelo de embeddings
    'hf_stt_model' => 'jonatasgrosman/wav2vec2-xlsr-53-spanish', // Modelo Speech-to-Text (economico por defecto)
    
    // Parámetros de optimización de costos
    'hf_max_length' => 500, // Longitud máxima de respuesta
    'hf_temperature' => 0.2, // Temperature baja para tareas determinísticas
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
];
