<?php

namespace common\components;

use Yii;
use yii\httpclient\Client;
use common\components\ConsultaLogger;

/**
 * Componente para manejar todas las interacciones con IA
 * Centraliza la lógica de proveedores, configuración y procesamiento
 */
class IAManager
{
    /**
     * Extraer ID del modelo desde endpoint
     * @param string $endpoint
     * @return string
     */
    private static function extraerModeloId($endpoint)
    {
        // Extraer nombre del modelo del endpoint
        // Ejemplo: https://api-inference.huggingface.co/models/llama3.1:8b -> llama3.1:8b
        if (preg_match('/models\/([^\/]+)/', $endpoint, $matches)) {
            return $matches[1];
        }
        return md5($endpoint);
    }
    
    /**
     * Comprimir datos en tránsito (gzip)
     * @param string $data Datos a comprimir
     * @return array ['data' => string, 'headers' => array]
     */
    private static function comprimirDatos($data)
    {
        $usarCompresion = Yii::$app->params['comprimir_datos_transito'] ?? true;
        $headers = [];
        
        if ($usarCompresion && function_exists('gzencode') && strlen($data) > 500) {
            // Solo comprimir si los datos son grandes (>500 bytes)
            $dataComprimida = gzencode($data, 6); // Nivel 6 (balance entre velocidad y compresión)
            $headers['Content-Encoding'] = 'gzip';
            \Yii::info("Datos comprimidos: " . strlen($data) . " -> " . strlen($dataComprimida) . " bytes", 'ia-manager');
            return ['data' => $dataComprimida, 'headers' => $headers];
        }
        
        return ['data' => $data, 'headers' => $headers];
    }
    
    /**
     * Obtener configuración del proveedor de IA
     * @return array
     */
    public static function getProveedorIA()
    {
        // Delegar a la instancia registrada para compatibilidad
        return Yii::$app->iamanager->getProveedorIAInstance();
    }

    /**
     * Implementación de instancia para obtener la configuración del proveedor de IA
     * @return array
     */
    public function getProveedorIAInstance()
    {
        // Configuración por defecto - Ollama local
        $proveedor = Yii::$app->params['ia_proveedor'] ?? 'ollama';
        
        switch ($proveedor) {
            case 'openai':
                return self::getConfiguracionOpenAI();
            case 'groq':
                return self::getConfiguracionGroq();
            case 'huggingface':
                return self::getConfiguracionHuggingFace();
            case 'ollama':
            default:
                return self::getConfiguracionOllama();
        }
    }

    /**
     * Configuración para Ollama (local)
     * Usa Llama 3.1 70B Instruct para máxima precisión en corrección ortográfica
     * @return array
     */
    private static function getConfiguracionOllama()
    {
        return [
            'tipo' => 'ollama',
            'endpoint' => 'http://localhost:11434/api/generate',
            'headers' => ['Content-Type' => 'application/json'],
            'payload' => [
                'model' => 'llama3.1:70b',
                'prompt' => '',
                'stream' => false,
                'options' => [
                    'temperature' => 0.0, // Mínima aleatoriedad para máxima precisión
                    'top_p' => 0.9,
                    'top_k' => 40,
                    'num_predict' => 4096, // Suficiente para textos médicos largos
                    'repeat_penalty' => 1.1
                ]
            ]
        ];
    }

    /**
     * Configuración para Groq
     * @return array
     */
    private static function getConfiguracionGroq()
    {
        return [
            'tipo' => 'groq',
            'endpoint' => 'https://api.groq.com/openai/v1/chat/completions',
            'headers' => [
                'Authorization' => 'Bearer ' . (Yii::$app->params['groq_api_key'] ?? ''),
                'Content-Type' => 'application/json'
            ],
            'payload' => [
                'model' => 'openai/gpt-oss-120b',
                'messages' => [
                    ['role' => 'user', 'content' => '']
                ],
                'max_completion_tokens' => 8192,
                'temperature' => 1,
                'top_p' => 1
            ]
        ];
    }

    /**
     * Configuración para OpenAI
     * @return array
     */
    private static function getConfiguracionOpenAI()
    {
        return [
            'tipo' => 'openai',
            'endpoint' => 'https://api.openai.com/v1/chat/completions',
            'headers' => [
                'Authorization' => 'Bearer ' . (Yii::$app->params['openai_api_key'] ?? ''),
                'OpenAI-Organization' => 'org-E9vasCzjdBU9rnnizXrWV032',
                'OpenAI-Project' => 'proj_PVE3UFOdCED5T55jhxToQD2R',
                'Content-Type' => 'application/json'
            ],
            'payload' => [
                'model' => 'gpt-3.5-turbo',
                'messages' => [
                    ['role' => 'user', 'content' => '']
                ],
                'max_tokens' => 1000
            ]
        ];
    }

    /**
     * Configuración para Hugging Face
     * Modelos optimizados para español y costo reducido
     * @param string $tipoModelo Tipo de modelo: 'text-generation', 'text-correction', 'analysis'
     * @return array
     */
    private static function getConfiguracionHuggingFace($tipoModelo = 'text-generation')
    {
        // Seleccionar modelo según el tipo de tarea
        $modelos = [
            'text-generation' => Yii::$app->params['hf_model_text_gen'] ?? 'HuggingFaceH4/zephyr-7b-beta',
            'text-correction' => Yii::$app->params['hf_model_correction'] ?? 'PlanTL-GOB-ES/roberta-base-biomedical-clinical-es',
            'analysis' => Yii::$app->params['hf_model_analysis'] ?? 'microsoft/DialoGPT-small',
        ];
        
        $modelo = $modelos[$tipoModelo] ?? $modelos['text-generation'];
        
        return [
            'tipo' => 'huggingface',
            'endpoint' => "https://api-inference.huggingface.co/models/{$modelo}",
            'headers' => [
                'Authorization' => 'Bearer ' . (Yii::$app->params['hf_api_key'] ?? ''),
                'Content-Type' => 'application/json'
            ],
            'payload' => [
                'inputs' => '',
                'parameters' => [
                    'max_length' => (int)(Yii::$app->params['hf_max_length'] ?? 500),
                    'temperature' => (float)(Yii::$app->params['hf_temperature'] ?? 0.2), // Más bajo para tareas determinísticas
                    'return_full_text' => false,
                    'wait_for_model' => false // Evitar cold starts costosos
                ]
            ],
            'modelo' => $modelo,
            'tipo_modelo' => $tipoModelo
        ];
    }

    /**
     * Asignar el prompt a la configuración del proveedor
     * @param array $proveedorIA
     * @param string $prompt
     */
    public static function asignarPromptAConfiguracion(&$proveedorIA, $prompt)
    {
        return Yii::$app->iamanager->asignarPromptAConfiguracionInstance($proveedorIA, $prompt);
    }

    /**
     * Implementación de instancia para asignar prompt a la configuración del proveedor
     * @param array $proveedorIA
     * @param string $prompt
     */
    public function asignarPromptAConfiguracionInstance(&$proveedorIA, $prompt)
    {
        switch ($proveedorIA['tipo']) {
            case 'ollama':
                $proveedorIA['payload']['prompt'] = $prompt;
                break;
            case 'openai':
            case 'groq':
                $proveedorIA['payload']['messages'][] = ['role' => 'user', 'content' => $prompt];
                break;
            case 'huggingface':
                $proveedorIA['payload']['inputs'] = $prompt;
                break;
        }
    }

    /**
     * Procesar respuesta según el tipo de proveedor
     * @param \yii\httpclient\Response $response
     * @param string $tipo
     * @return string|null
     */
    public static function procesarRespuestaProveedor($response, $tipo)
    {
        return Yii::$app->iamanager->procesarRespuestaProveedorInstance($response, $tipo);
    }

    /**
     * Implementación de instancia para procesar la respuesta según proveedor
     * @param \yii\httpclient\Response $response
     * @param string $tipo
     * @return string|null
     */
    public function procesarRespuestaProveedorInstance($response, $tipo)
    {
        $responseData = json_decode($response->content, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            \Yii::error('Error decodificando JSON de IA: ' . json_last_error_msg(), 'ia-manager');
            return null;
        }

        $contenido = null;
        switch ($tipo) {
            case 'ollama':
                $contenido = $responseData['response'] ?? null;
                break;
            case 'openai':
            case 'groq':
                $contenido = $responseData['choices'][0]['message']['content'] ?? null;
                break;
            case 'huggingface':
                $contenido = $responseData[0]['generated_text'] ?? null;
                break;
            default:
                $contenido = $responseData;
        }

        return $contenido;
    }

    /**
     * Enviar consulta a IA y obtener respuesta
     * @param string $prompt
     * @param string $contexto (opcional, para logging)
     * @param string $tipoModelo Tipo de modelo para HuggingFace: 'text-generation', 'text-correction', 'analysis'
     * @return array|null
     */
    public static function consultarIA($prompt, $contexto = 'consulta-general', $tipoModelo = 'text-generation')
    {
        $logger = ConsultaLogger::obtenerInstancia();
        
        try {
            // Verificar deduplicación primero
            $deduplicado = \common\components\RequestDeduplicator::buscarSimilar($prompt, $contexto);
            if ($deduplicado !== null) {
                \Yii::info("Request duplicado encontrado para: {$contexto}", 'ia-manager');
                return $deduplicado;
            }
            
            // Verificar cache
            $cacheKey = 'ia_response_' . md5($prompt . $contexto . $tipoModelo);
            $yiiCache = Yii::$app->cache;
            if ($yiiCache) {
                $cached = $yiiCache->get($cacheKey);
                if ($cached !== false) {
                    \Yii::info("Respuesta de IA obtenida desde cache para: {$contexto}", 'ia-manager');
                    // Guardar en deduplicador también
                    \common\components\RequestDeduplicator::guardar($prompt, $cached, $contexto);
                    return $cached;
                }
            }
            
            // Verificar rate limiter
            $endpoint = '';
            $proveedorIA = self::getProveedorIA();
            
            // Si es HuggingFace, usar el tipo de modelo específico
            if ($proveedorIA['tipo'] === 'huggingface') {
                $proveedorIA = self::getConfiguracionHuggingFace($tipoModelo);
                $endpoint = $proveedorIA['endpoint'];
                
                // Registrar uso del modelo para gestión de memoria
                $modeloId = self::extraerModeloId($endpoint);
                ModelManager::registrarUso($modeloId, $tipoModelo);
                
                // Verificar si el modelo debe estar cargado
                if (!ModelManager::debeEstarCargado($modeloId, $tipoModelo)) {
                    \Yii::warning("Modelo no disponible en memoria: {$modeloId}. Cargando...", 'ia-manager');
                    // En un sistema real, aquí se cargaría el modelo
                }
                
                // Verificar rate limiter
                if (!\common\components\HuggingFaceRateLimiter::puedeHacerRequest($endpoint, false)) {
                    \Yii::warning("Rate limit alcanzado para: {$endpoint}", 'ia-manager');
                    return null;
                }
            }
            
            // Asignar el prompt
            self::asignarPromptAConfiguracion($proveedorIA, $prompt);
            
            // Registrar prompt enviado
            if ($logger) {
                $logger->registrar(
                    'ANÁLISIS IA',
                    $prompt,
                    null,
                    [
                        'metodo' => 'IAManager::consultarIA',
                        'proveedor' => $proveedorIA['tipo'] ?? 'desconocido'
                    ]
                );
            }
            
            // Los logs detallados ya se manejan en ConsultaLogger

            // Comprimir datos en tránsito (gzip) para reducir ancho de banda
            $payloadJson = json_encode($proveedorIA['payload']);
            $compresion = self::comprimirDatos($payloadJson);
            $headersConCompresion = array_merge($proveedorIA['headers'], $compresion['headers']);
            
            $client = new Client();
            $response = $client->createRequest()
                ->setMethod('POST')
                ->setUrl($proveedorIA['endpoint'])
                ->addHeaders($headersConCompresion)
                ->setContent($compresion['data'])
                ->send();

            if ($response->isOk) {
                // Los logs detallados ya se manejan en ConsultaLogger
                $responseData = self::procesarRespuestaProveedor($response, $proveedorIA['tipo']);
                
                if ($responseData) {
                    $resultado = self::validarYLimpiarRespuestaJSON($responseData);
                    
                    // Registrar éxito en rate limiter
                    if ($proveedorIA['tipo'] === 'huggingface' && !empty($endpoint)) {
                        \common\components\HuggingFaceRateLimiter::registrarExito($endpoint);
                    }
                    
                    // Guardar en cache y deduplicador si es válido
                    if ($resultado) {
                        if ($yiiCache) {
                            $ttl = (int)(Yii::$app->params['ia_cache_ttl'] ?? 3600);
                            $yiiCache->set($cacheKey, $resultado, $ttl);
                        }
                        // Guardar en deduplicador
                        \common\components\RequestDeduplicator::guardar($prompt, $resultado, $contexto);
                    }
                    
                    // Registrar respuesta recibida
                    if ($logger) {
                        $logger->registrar(
                            'ANÁLISIS IA',
                            null,
                            $resultado ? 'Respuesta procesada exitosamente' : 'Error procesando respuesta',
                            [
                                'metodo' => 'IAManager::consultarIA',
                                'status_code' => $response->getStatusCode(),
                                'respuesta_length' => $responseData ? strlen($responseData) : 0
                            ]
                        );
                    }
                    
                    return $resultado;
                }
            } else {
                // Registrar error en rate limiter
                if ($proveedorIA['tipo'] === 'huggingface' && !empty($endpoint)) {
                    \common\components\HuggingFaceRateLimiter::registrarError($endpoint, $response->getStatusCode());
                }
                
                \Yii::error("Error en la respuesta de la IA para {$contexto}: " . $response->getStatusCode() . ' - ' . $response->getContent(), 'ia-manager');
                
                // Registrar error
                if ($logger) {
                    $logger->registrar(
                        'ANÁLISIS IA',
                        null,
                        'Error en respuesta de IA',
                        [
                            'metodo' => 'IAManager::consultarIA',
                            'status_code' => $response->getStatusCode(),
                            'error' => substr($response->getContent(), 0, 200)
                        ]
                    );
                }
            }

        } catch (\Exception $e) {
            \Yii::error("Error consultando IA para {$contexto}: " . $e->getMessage(), 'ia-manager');
            
            // Registrar excepción
            if ($logger) {
                $logger->registrar(
                    'ANÁLISIS IA',
                    null,
                    'Excepción en consulta IA',
                    [
                        'metodo' => 'IAManager::consultarIA',
                        'error' => $e->getMessage()
                    ]
                );
            }
        }

        return null;
    }

    /**
     * Método de instancia que delega a la implementación estática para compatibilidad.
     * @param string $prompt
     * @param string $contexto
     * @return array|null
     */
    public function consultar($prompt, $contexto = 'consulta-general')
    {
        return self::consultarIA($prompt, $contexto);
    }

    /**
     * Validar y limpiar respuesta JSON de la IA
     * @param string $respuesta
     * @return array|null
     */
    private static function validarYLimpiarRespuestaJSON($respuesta)
    {
        // Delegar a la implementación de instancia
        return Yii::$app->iamanager->validarYLimpiarRespuestaJSONInstance($respuesta);
    }

    /**
     * Limpiar respuesta JSON de caracteres problemáticos
     * @param string $respuesta
     * @return string
     */
    private static function limpiarRespuestaJSON($respuesta)
    {
        // Delegar a la implementación de instancia
        return Yii::$app->iamanager->limpiarRespuestaJSONInstance($respuesta);
    }

    /**
     * Obtener términos contextuales usando IA (para SNOMED)
     * @param string $prompt
     * @return array
     */
    public static function obtenerTerminosContextuales($prompt)
    {
        return Yii::$app->iamanager->obtenerTerminosContextualesInstance($prompt);
    }

    /**
     * Crear prompt contextual para embeddings SNOMED
     * @param string $texto
     * @param string $categoria
     * @return string
     */
    public static function crearPromptContextual($texto, $categoria)
    {
        $contextosCategoria = [
            'diagnosticos' => 'diagnósticos médicos, enfermedades, condiciones patológicas, hallazgos clínicos',
            'sintomas' => 'síntomas, signos, manifestaciones clínicas, motivos de consulta',
            'medicamentos' => 'medicamentos, fármacos, sustancias farmacológicas, tratamientos',
            'procedimientos' => 'procedimientos médicos, intervenciones, técnicas diagnósticas, exámenes'
        ];
        
        $contexto = $contextosCategoria[$categoria] ?? 'conceptos médicos';
        
        return "Analiza el siguiente término médico en el contexto de {$contexto} y proporciona el término SNOMED CT más apropiado y preciso. 

Término: '{$texto}'
Contexto: {$contexto}

Responde SOLO con el término SNOMED CT más preciso, sin explicaciones adicionales:";
    }

    /**
     * Corregir una palabra (intenta CPU primero, luego LLM si es necesario)
     * @param string $palabra
     * @param string $contexto
     * @param string|null $especialidad
     * @return array|null
     */
    public function corregirPalabraConLLM($palabra, $contexto, $especialidad = null)
    {
        // Intentar corrección básica con CPU primero
        $usarCPU = Yii::$app->params['usar_cpu_tareas_simples'] ?? true;
        if ($usarCPU && CPUProcessor::puedeProcesarConCPU('correccion_ortografica_basica')) {
            $corregidaCPU = CPUProcessor::procesar('correccion_ortografica_basica', $palabra);
            if ($corregidaCPU !== $palabra) {
                \Yii::info("Corrección CPU aplicada: '{$palabra}' -> '{$corregidaCPU}'", 'ia-manager');
                return [
                    'suggestion' => $corregidaCPU,
                    'confidence' => 0.7,
                    'metodo' => 'cpu'
                ];
            }
        }
        
        // Si CPU no corrigió, usar LLM
        return $this->corregirPalabraConLLMReal($palabra, $contexto, $especialidad);
    }
    
    /**
     * Corregir una palabra usando LLM (mismo comportamiento que en ProcesadorTextoMedico)
     * @param string $palabra
     * @param string $contexto
     * @param string|null $especialidad
     * @return array|null
     */
    private function corregirPalabraConLLMReal($palabra, $contexto, $especialidad = null)
    {
        try {
            // Mejor prompt que enfatiza el contexto
            $prompt = "Eres un especialista médico en {$especialidad}. Analiza la siguiente oración médica y corrige SOLO la palabra indicada si tiene un error ortográfico. Si la palabra está correcta en el contexto, no la cambies.\n\n";
            $prompt .= "Oración: {$contexto}\n";
            $prompt .= "Palabra a revisar: {$palabra}\n";
            $prompt .= "Instrucciones:\n";
            $prompt .= "- Si la palabra tiene un error ortográfico, responde SOLO con la palabra corregida\n";
            $prompt .= "- Si la palabra está correcta (incluyendo preposiciones, artículos, etc.), responde con la misma palabra\n";
            $prompt .= "- Considera el contexto médico de la oración\n";
            $prompt .= "Corrección:";

            $endpoint = \Yii::$app->params['hf_endpoint'] ?? 'https://api-inference.huggingface.co/models/PlanTL-GOB-ES/roberta-base-biomedical-clinical-es';
            $apiKey = \Yii::$app->params['hf_api_key'] ?? '';

            $payload = [
                'inputs' => $prompt,
                'parameters' => [
                    'max_length' => 50,
                    'temperature' => 0.1,
                    'return_full_text' => false
                ]
            ];

            // Comprimir datos en tránsito
            $payloadJson = json_encode($payload);
            $compresion = self::comprimirDatos($payloadJson);
            $headers = [
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json'
            ];
            $headers = array_merge($headers, $compresion['headers']);
            
            $client = new Client();
            $response = $client->createRequest()
                ->setMethod('POST')
                ->setUrl($endpoint)
                ->addHeaders($headers)
                ->setContent($compresion['data'])
                ->send();

            if ($response->isOk) {
                $data = json_decode($response->content, true);
                $suggestion = trim($data[0]['generated_text'] ?? '');

                if (!empty($suggestion) && $suggestion !== $palabra) {
                    $confianza = $this->calcularConfianzaLLM($palabra, $suggestion, $contexto);
                    return [
                        'suggestion' => $suggestion,
                        'confidence' => $confianza
                    ];
                }
            }

            return null;

        } catch (\Exception $e) {
            \Yii::error("Error corrigiendo palabra con LLM (IAManager): " . $e->getMessage(), 'ia-manager');
            return null;
        }
    }

    /**
     * Corregir múltiples palabras usando LLM en un solo prompt
     * @param array $palabras Array de palabras a corregir
     * @param string $contexto Texto completo donde aparecen las palabras
     * @param string|null $especialidad
     * @return array Array asociativo con las correcciones: ['palabra' => ['suggestion' => '...', 'confidence' => 0.8]]
     */
    public function corregirPalabrasConLLM($palabras, $contexto, $especialidad = null)
    {
        try {
            if (empty($palabras)) {
                return [];
            }

            // Crear prompt para corregir todas las palabras de una vez
            $palabrasLista = implode(', ', array_unique($palabras));
            $prompt = "Eres un especialista médico en {$especialidad}. Analiza el siguiente texto médico y corrige SOLO las palabras que tienen errores ortográficos. Si una palabra está correcta, repite la misma palabra.\n\n";
            $prompt .= "Texto: {$contexto}\n";
            $prompt .= "Palabras a revisar: {$palabrasLista}\n";
            $prompt .= "Para cada palabra, escribe: palabra_original -> palabra_corregida (o palabra_original si está correcta)\n";
            $prompt .= "Correcciones:\n";

            $endpoint = \Yii::$app->params['hf_endpoint'] ?? 'https://api-inference.huggingface.co/models/PlanTL-GOB-ES/roberta-base-biomedical-clinical-es';
            $apiKey = \Yii::$app->params['hf_api_key'] ?? '';

            $payload = [
                'inputs' => $prompt,
                'parameters' => [
                    'max_length' => 200, // Aumentar para múltiples palabras
                    'temperature' => 0.1,
                    'return_full_text' => false
                ]
            ];

            $logger = ConsultaLogger::obtenerInstancia();
            if ($logger) {
                $logger->registrar(
                    'PROCESAMIENTO',
                    'Enviando petición LLM para corrección múltiple',
                    'Procesando ' . count($palabras) . ' palabras en un solo prompt',
                    [
                        'metodo' => 'IAManager::corregirPalabrasConLLM',
                        'total_palabras' => count($palabras),
                        'palabras' => $palabras,
                        'endpoint' => $endpoint
                    ]
                );
            }

            // Comprimir datos en tránsito
            $payloadJson = json_encode($payload);
            $compresion = self::comprimirDatos($payloadJson);
            $headers = [
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json'
            ];
            $headers = array_merge($headers, $compresion['headers']);
            
            $client = new Client();
            $response = $client->createRequest()
                ->setMethod('POST')
                ->setUrl($endpoint)
                ->addHeaders($headers)
                ->setContent($compresion['data'])
                ->send();

            if ($response->isOk) {
                $data = json_decode($response->content, true);
                $respuesta = trim($data[0]['generated_text'] ?? '');
                
                if ($logger) {
                    $logger->registrar(
                        'PROCESAMIENTO',
                        'Respuesta LLM recibida',
                        'Respuesta procesada para ' . count($palabras) . ' palabras',
                        [
                            'metodo' => 'IAManager::corregirPalabrasConLLM',
                            'respuesta_length' => strlen($respuesta),
                            'total_palabras' => count($palabras)
                        ]
                    );
                }
                
                $correcciones = [];
                
                // Intentar parsear formato: palabra_original -> palabra_corregida
                foreach ($palabras as $palabra) {
                    // Buscar patrones como "palabra -> correccion" o "palabra: correccion" o "palabra correccion"
                    $patrones = [
                        '/\b' . preg_quote($palabra, '/') . '\s*->\s*([a-zA-ZáéíóúüñÁÉÍÓÚÜÑ]+)/i',
                        '/\b' . preg_quote($palabra, '/') . '\s*:\s*([a-zA-ZáéíóúüñÁÉÍÓÚÜÑ]+)/i',
                        '/\b' . preg_quote($palabra, '/') . '\s+([a-zA-ZáéíóúüñÁÉÍÓÚÜÑ]{3,})/i',
                    ];
                    
                    foreach ($patrones as $pattern) {
                        if (preg_match($pattern, $respuesta, $matches)) {
                            $suggestion = trim($matches[1]);
                            // Si la sugerencia es diferente a la palabra original, es una corrección
                            if (!empty($suggestion) && strtolower($suggestion) !== strtolower($palabra)) {
                                $confianza = $this->calcularConfianzaLLM($palabra, $suggestion, $contexto);
                                $correcciones[$palabra] = [
                                    'suggestion' => $suggestion,
                                    'confidence' => $confianza
                                ];
                                break; // Salir del bucle de patrones si encontramos una coincidencia
                            }
                        }
                    }
                }
                
                // Si no encontramos correcciones con patrones, intentar parsear JSON como fallback
                if (empty($correcciones)) {
                    $jsonMatch = [];
                    if (preg_match('/\{[^}]+\}/', $respuesta, $jsonMatch)) {
                        $jsonData = json_decode($jsonMatch[0], true);
                        if (is_array($jsonData)) {
                            foreach ($palabras as $palabra) {
                                if (isset($jsonData[$palabra])) {
                                    $suggestion = trim($jsonData[$palabra]);
                                    if (!empty($suggestion) && strtolower($suggestion) !== strtolower($palabra)) {
                                        $confianza = $this->calcularConfianzaLLM($palabra, $suggestion, $contexto);
                                        $correcciones[$palabra] = [
                                            'suggestion' => $suggestion,
                                            'confidence' => $confianza
                                        ];
                                    }
                                }
                            }
                        }
                    }
                }
                
                if ($logger && !empty($correcciones)) {
                    $logger->registrar(
                        'PROCESAMIENTO',
                        'Correcciones LLM aplicadas',
                        'Se encontraron ' . count($correcciones) . ' correcciones de ' . count($palabras) . ' palabras',
                        [
                            'metodo' => 'IAManager::corregirPalabrasConLLM',
                            'total_correcciones' => count($correcciones),
                            'correcciones' => array_keys($correcciones)
                        ]
                    );
                }

                return $correcciones;
            }

            if ($logger) {
                $logger->registrar(
                    'ERROR',
                    'Error en respuesta LLM',
                    'La respuesta del LLM no fue exitosa',
                    [
                        'metodo' => 'IAManager::corregirPalabrasConLLM',
                        'status_code' => $response->statusCode ?? 'unknown'
                    ]
                );
            }

            return [];

        } catch (\Exception $e) {
            \Yii::error("Error corrigiendo palabras con LLM (IAManager): " . $e->getMessage(), 'ia-manager');
            return [];
        }
    }

    /**
     * Corregir texto médico completo usando modelo de IA local (Ollama)
     * Usa Llama 3.1 70B Instruct para máxima precisión
     * @param string $texto Texto original a corregir
     * @param string|null $especialidad Especialidad médica
     * @return array ['texto_corregido' => string, 'cambios' => array, 'confidence' => float]
     */
    public function corregirTextoCompletoConIA($texto, $especialidad = null)
    {
        $logger = ConsultaLogger::obtenerInstancia();
        $inicio = microtime(true);
        
        try {
            // Verificar cache primero
            $cacheKey = 'correccion_texto_' . md5($texto . ($especialidad ?? ''));
            $yiiCache = Yii::$app->cache;
            if ($yiiCache) {
                $cached = $yiiCache->get($cacheKey);
                if ($cached !== false && is_array($cached)) {
                    \Yii::info("Corrección de texto obtenida desde cache", 'ia-manager');
                    return $cached;
                }
            }
            
            // Obtener configuración del proveedor (Ollama por defecto)
            $proveedorIA = $this->getProveedorIAInstance();
            
            // Crear prompt optimizado (más corto para reducir costos)
            $prompt = "Corrige errores ortográficos y expande abreviaturas médicas en español. Responde SOLO con el texto corregido, sin explicaciones.

Reglas:
- Corrige errores ortográficos reales
- Expande abreviaturas médicas (OI→ojo izquierdo, OD→ojo derecho)
- Mantén formato y puntuación original

Texto: {$texto}

Corregido:";

            // Asignar prompt según el tipo de proveedor
            $this->asignarPromptAConfiguracionInstance($proveedorIA, $prompt);
            
            if ($logger) {
                $logger->registrar(
                    'PROCESAMIENTO',
                    'Enviando texto completo para corrección con IA',
                    'Procesando texto completo con modelo local (Llama 3.1 70B Instruct)',
                    [
                        'metodo' => 'IAManager::corregirTextoCompletoConIA',
                        'proveedor' => $proveedorIA['tipo'] ?? 'ollama',
                        'modelo' => $proveedorIA['payload']['model'] ?? 'llama3.1:70b-instruct',
                        'longitud_texto' => strlen($texto),
                        'especialidad' => $especialidad
                    ]
                );
            }
            
            // Realizar petición
            $client = new Client();
            $response = $client->createRequest()
                ->setMethod('POST')
                ->setUrl($proveedorIA['endpoint'])
                ->addHeaders($proveedorIA['headers'] ?? [])
                ->setContent(json_encode($proveedorIA['payload']))
                ->send();
            
            if ($response->isOk) {
                $textoCorregido = $this->procesarRespuestaProveedorInstance($response, $proveedorIA['tipo']);
                
                if ($textoCorregido) {
                    $textoCorregido = trim($textoCorregido);
                    
                    // Limpiar posibles prefijos/sufijos que el modelo pueda agregar
                    $textoCorregido = preg_replace('/^(Texto corregido|Corrección|Corregido):\s*/i', '', $textoCorregido);
                    $textoCorregido = trim($textoCorregido);
                    
                    // Detectar cambios comparando texto original y corregido
                    $cambios = $this->detectarCambios($texto, $textoCorregido);
                    $confidence = $this->calcularConfianzaCorreccion($texto, $textoCorregido, $cambios);
                    
                    $tiempoProcesamiento = microtime(true) - $inicio;
                    
                    if ($logger) {
                        $cambiosDetallados = [];
                        foreach ($cambios as $cambio) {
                            $cambiosDetallados[] = $cambio['original'] . ' → ' . $cambio['corrected'];
                        }
                        
                        $logger->registrar(
                            'PROCESAMIENTO',
                            'Corrección IA completada',
                            'Texto corregido exitosamente',
                            [
                                'metodo' => 'IAManager::corregirTextoCompletoConIA',
                                'total_cambios' => count($cambios),
                                'confidence' => $confidence,
                                'tiempo' => round($tiempoProcesamiento, 3),
                                'cambios_detallados' => $cambiosDetallados
                            ]
                        );
                    }
                    
                    $resultado = [
                        'texto_corregido' => $textoCorregido,
                        'cambios' => $cambios,
                        'confidence' => $confidence,
                        'total_changes' => count($cambios),
                        'processing_time' => $tiempoProcesamiento,
                        'metodo' => 'ia_local'
                    ];
                    
                    // Guardar en cache
                    if ($yiiCache) {
                        $ttl = (int)(Yii::$app->params['correccion_cache_ttl'] ?? 7200); // 2 horas para correcciones
                        $yiiCache->set($cacheKey, $resultado, $ttl);
                    }
                    
                    return $resultado;
                }
            }
            
            // Si falla, retornar texto original sin cambios
            if ($logger) {
                $logger->registrar(
                    'ERROR',
                    'Error en corrección IA',
                    'No se pudo corregir el texto, retornando original',
                    [
                        'metodo' => 'IAManager::corregirTextoCompletoConIA',
                        'status_code' => $response->statusCode ?? 'unknown',
                        'response_content' => substr($response->content ?? '', 0, 200)
                    ]
                );
            }
            
            return [
                'texto_corregido' => $texto,
                'cambios' => [],
                'confidence' => 0,
                'total_changes' => 0,
                'processing_time' => microtime(true) - $inicio,
                'metodo' => 'ia_local_fallback'
            ];
            
        } catch (\Exception $e) {
            \Yii::error("Error en corrección IA completa: " . $e->getMessage(), 'ia-manager');
            
            if ($logger) {
                $logger->registrar(
                    'ERROR',
                    'Excepción en corrección IA',
                    $e->getMessage(),
                    [
                        'metodo' => 'IAManager::corregirTextoCompletoConIA',
                        'exception' => get_class($e)
                    ]
                );
            }
            
            return [
                'texto_corregido' => $texto,
                'cambios' => [],
                'confidence' => 0,
                'total_changes' => 0,
                'processing_time' => 0,
                'metodo' => 'ia_local_error'
            ];
        }
    }

    /**
     * Detectar cambios entre texto original y corregido
     * @param string $original
     * @param string $corregido
     * @return array
     */
    private function detectarCambios($original, $corregido)
    {
        $cambios = [];
        
        // Si los textos son idénticos, no hay cambios
        if (trim($original) === trim($corregido)) {
            return $cambios;
        }
        
        // Dividir en palabras preservando espacios
        $palabrasOriginales = preg_split('/(\s+)/', $original, -1, PREG_SPLIT_DELIM_CAPTURE);
        $palabrasCorregidas = preg_split('/(\s+)/', $corregido, -1, PREG_SPLIT_DELIM_CAPTURE);
        
        // Comparar palabra por palabra
        $maxLen = max(count($palabrasOriginales), count($palabrasCorregidas));
        
        for ($i = 0; $i < $maxLen; $i++) {
            $palabraOriginal = $palabrasOriginales[$i] ?? '';
            $palabraCorregida = $palabrasCorregidas[$i] ?? '';
            
            // Saltar espacios
            if (trim($palabraOriginal) === '' || trim($palabraCorregida) === '') {
                continue;
            }
            
            // Limpiar puntuación para comparar
            $originalLimpia = preg_replace('/[^a-zA-ZáéíóúüñÁÉÍÓÚÜÑ]/', '', $palabraOriginal);
            $corregidaLimpia = preg_replace('/[^a-zA-ZáéíóúüñÁÉÍÓÚÜÑ]/', '', $palabraCorregida);
            
            if (strtolower($originalLimpia) !== strtolower($corregidaLimpia) && 
                !empty($originalLimpia) && !empty($corregidaLimpia)) {
                $cambios[] = [
                    'original' => $palabraOriginal,
                    'corrected' => $palabraCorregida,
                    'confidence' => 0.95, // Alta confianza para correcciones de IA
                    'method' => 'ia_local'
                ];
            }
        }
        
        return $cambios;
    }

    /**
     * Calcular confianza de la corrección
     * @param string $original
     * @param string $corregido
     * @param array $cambios
     * @return float
     */
    private function calcularConfianzaCorreccion($original, $corregido, $cambios)
    {
        if (empty($cambios)) {
            // Si no hay cambios, alta confianza (texto ya estaba correcto)
            return 0.95;
        }
        
        // Confianza basada en número de cambios vs longitud del texto
        $longitudTexto = strlen($original);
        $numPalabras = str_word_count($original);
        $ratioCambios = count($cambios) / max($numPalabras, 1);
        
        // Menos cambios = mayor confianza
        if ($ratioCambios < 0.1) {
            return 0.95; // Alta confianza
        } elseif ($ratioCambios < 0.2) {
            return 0.85; // Buena confianza
        } else {
            return 0.75; // Confianza moderada
        }
    }

    /**
     * Calcular confianza de la corrección del LLM (mismo algoritmo que ProcesadorTextoMedico)
     * @param string $original
     * @param string $suggestion
     * @param string $contexto
     * @return float
     */
    public function calcularConfianzaLLM($original, $suggestion, $contexto)
    {
        $confianza = 0.5;

        $similitud = 1 - (levenshtein($original, $suggestion) / max(strlen($original), strlen($suggestion)));
        $confianza += $similitud * 0.3;

        $terminosMedicos = ['paciente', 'consulta', 'diagnóstico', 'tratamiento', 'medicamento', 'síntoma', 'enfermedad'];
        foreach ($terminosMedicos as $termino) {
            if (stripos($contexto, $termino) !== false) {
                $confianza += 0.1;
                break;
            }
        }

        if (strlen($suggestion) < 3) {
            $confianza -= 0.2;
        }

        return min(1.0, max(0.0, $confianza));
    }

    /**
     * Implementación de instancia para validar y limpiar respuesta JSON
     * @param string $respuesta
     * @return array|null
     */
    public function validarYLimpiarRespuestaJSONInstance($respuesta)
    {
        // Limpiar la respuesta de posibles caracteres extra
        $respuesta = trim($respuesta);

        // Buscar JSON en la respuesta (por si hay texto adicional)
        if (preg_match('/\{.*\}/s', $respuesta, $matches)) {
            $respuesta = $matches[0];
        }

        // Intentar decodificar JSON
        $jsonData = json_decode($respuesta, true);

        if (json_last_error() === JSON_ERROR_NONE) {
            // JSON válido - los logs detallados ya se manejan en ConsultaLogger
            return $jsonData;
        }

        // Si falla, intentar limpiar más
        $respuestaLimpia = $this->limpiarRespuestaJSONInstance($respuesta);
        $jsonData = json_decode($respuestaLimpia, true);

        if (json_last_error() === JSON_ERROR_NONE) {
            // JSON válido - los logs detallados ya se manejan en ConsultaLogger
            return $jsonData;
        }

        \Yii::error('No se pudo decodificar JSON de la IA: ' . json_last_error_msg() . ' - Respuesta: ' . substr($respuesta, 0, 200), 'ia-manager');
        return null;
    }

    /**
     * Implementación de instancia para limpiar respuesta JSON
     * @param string $respuesta
     * @return string
     */
    public function limpiarRespuestaJSONInstance($respuesta)
    {
        // Remover caracteres de control y espacios extra
        $respuesta = preg_replace('/[\x00-\x1F\x7F]/', '', $respuesta);

        // Remover texto antes del primer {
        if (($pos = strpos($respuesta, '{')) !== false) {
            $respuesta = substr($respuesta, $pos);
        }

        // Remover texto después del último }
        if (($pos = strrpos($respuesta, '}')) !== false) {
            $respuesta = substr($respuesta, 0, $pos + 1);
        }

        return trim($respuesta);
    }

    /**
     * Implementación de instancia para obtener términos contextuales
     * @param string $prompt
     * @return array
     */
    public function obtenerTerminosContextualesInstance($prompt)
    {
        try {
            $resultado = $this->consultar($prompt, 'terminos-contextuales');

            if ($resultado && is_string($resultado)) {
                return [trim($resultado)];
            } elseif ($resultado && is_array($resultado)) {
                return $resultado;
            }

            return [];

        } catch (\Exception $e) {
            \Yii::error("Error obteniendo términos contextuales: " . $e->getMessage(), 'ia-manager');
            return [];
        }
    }
}
