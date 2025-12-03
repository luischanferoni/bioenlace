<?php

return [
    'adminEmail' => 'admin@example.com',
    'path' => '/frontend',
    'bsVersion' => '5.x',
    'vaCartelPaciente' => true,  // Para mostrar el cartel de que se esta trabajando con cierto paciente
    'botonera' => ['view' => false, 'params' => []], // para guardar el path de un partial en donde esten los botones
    
    // Configuración de IA
    'ia_proveedor' => 'ollama', // 'ollama', 'groq', 'openai', 'huggingface'
    'groq_api_key' => '', // API key para Groq
    'openai_api_key' => '', // API key para OpenAI
    'hf_api_key' => '', // API key para Hugging Face
];
