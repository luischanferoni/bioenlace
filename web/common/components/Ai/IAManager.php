<?php

namespace common\components\Ai;

use Yii;
use yii\httpclient\Client;
use common\components\Logging\ConsultaLogger;
use common\components\Ai\HuggingFace\HuggingFaceRateLimiter;
use common\components\Infra\Requests\RequestDeduplicator;

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
        // Ejemplo: https://router.huggingface.co/models/llama3.1:8b -> llama3.1:8b
        if (preg_match('/models\/([^\/]+)/', $endpoint, $matches)) {
            return $matches[1];
        }
        return md5($endpoint);
    }
    
    /**
     * Comprimir datos en tránsito (gzip)
     * @param string $data Datos a comprimir
     * @param string|null $tipoProveedor Tipo de proveedor ('huggingface', 'openai', 'groq', 'ollama', 'google')
     * @return array ['data' => string, 'headers' => array]
     */
    private static function comprimirDatos($data, $tipoProveedor = null)
    {
        // La nueva API router.huggingface.co podría no aceptar compresión gzip
        // Desactivar compresión temporalmente para evitar errores 422
        if ($tipoProveedor === 'huggingface') {
            return ['data' => $data, 'headers' => []];
        }
        
        // Solo comprimir para otros proveedores si lo aceptan
        $proveedoresQueAceptanCompresion = [];
        
        if ($tipoProveedor && !in_array($tipoProveedor, $proveedoresQueAceptanCompresion)) {
            // No comprimir para OpenAI, Groq, Ollama, etc.
            return ['data' => $data, 'headers' => []];
        }
        
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
     * Descomprimir respuesta HTTP si está comprimida
     * @param \yii\httpclient\Response $response
     * @return string Contenido descomprimido
     */
    private static function descomprimirRespuesta($response)
    {
        $content = $response->content;
        
        // Verificar si la respuesta está comprimida
        $contentEncoding = $response->headers->get('Content-Encoding', '');
        
        if (strtolower($contentEncoding) === 'gzip' || 
            (substr($content, 0, 2) === "\x1f\x8b")) { // Firma gzip
            if (function_exists('gzdecode')) {
                $descomprimido = @gzdecode($content);
                if ($descomprimido !== false) {
                    \Yii::info("Respuesta descomprimida: " . strlen($content) . " -> " . strlen($descomprimido) . " bytes", 'ia-manager');
                    return $descomprimido;
                } else {
                    \Yii::warning("Error descomprimiendo respuesta gzip", 'ia-manager');
                }
            }
        } elseif (strtolower($contentEncoding) === 'deflate') {
            if (function_exists('gzinflate')) {
                $descomprimido = @gzinflate($content);
                if ($descomprimido !== false) {
                    \Yii::info("Respuesta descomprimida (deflate): " . strlen($content) . " -> " . strlen($descomprimido) . " bytes", 'ia-manager');
                    return $descomprimido;
                } else {
                    \Yii::warning("Error descomprimiendo respuesta deflate", 'ia-manager');
                }
            }
        }
        
        return $content;
    }
    
    /**
     * Obtener configuración del proveedor de IA
     * @return array
     */
    public static function getProveedorIA($tipoModelo = null)
    {
        // Delegar a la instancia registrada para compatibilidad
        return Yii::$app->iamanager->getProveedorIAInstance($tipoModelo);
    }

    /**
     * Implementación de instancia para obtener la configuración del proveedor de IA
     * @param string|null $tipoModelo Tipo de modelo para HuggingFace: 'text-generation', 'text-correction', 'analysis'
     * @return array
     */
    public function getProveedorIAInstance($tipoModelo = null)
    {
        // Configuración por defecto - HuggingFace (Ollama no disponible sin infraestructura)
        $proveedor = Yii::$app->params['ia_proveedor'] ?? 'huggingface';
        
        switch ($proveedor) {
            case 'openai':
                return self::getConfiguracionOpenAI();
            case 'groq':
                return self::getConfiguracionGroq();
            case 'ollama':
                return self::getConfiguracionOllama();
            case 'google':
                return self::getConfiguracionGoogle();
            case 'huggingface':
            default:
                return self::getConfiguracionHuggingFace($tipoModelo);
        }
    }

    /**
     * Configuración para Ollama (local)
     * NOTA: Esta configuración no está disponible actualmente - requiere infraestructura/hardware local
     * (servidores con GPU, Ollama instalado). El código se mantiene para uso futuro.
     * Usa Llama 3.1 70B Instruct para máxima precisión en corrección ortográfica
     * @return array
     */
    private static function getConfiguracionOllama()
    {
        return [
            'tipo' => 'ollama',
            'endpoint' => 'http://192.168.1.11:11434/api/generate', // 190.30.242.228:1000
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
                'model' => 'llama3-70b-8192',
                'messages' => [
                    ['role' => 'user', 'content' => '']
                ],
                'max_completion_tokens' => 8192,
                'temperature' => 0.3,
                'top_p' => 0.9
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
     * Configuración para Google (Vertex AI / Gemini)
     * @return array
     */
    private static function getConfiguracionGoogle()
    {
        $projectId = Yii::$app->params['google_cloud_project_id'] ?? '';
        $location = Yii::$app->params['google_cloud_region'] ?? 'us-central1';
        $model = Yii::$app->params['vertex_ai_model'] ?? 'gemini-1.5-pro';
        
        // Alternativa: usar Generative AI API (más simple, requiere API key)
        $apiKey = Yii::$app->params['google_cloud_api_key'] ?? '';
        $usarGenerativeAI = !empty($apiKey);
        
        if ($usarGenerativeAI) {
            // Generative AI API (con API key) - endpoint público
            $endpoint = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent";
        } else {
            // Vertex AI (con cuenta de servicio) - endpoint para modelos Gemini
            $endpoint = "https://{$location}-aiplatform.googleapis.com/v1/projects/{$projectId}/locations/{$location}/publishers/google/models/{$model}:generateContent";
        }
        
        $headers = [
            'Content-Type' => 'application/json'
        ];
        
        // Si hay API key, usarla en lugar de autenticación de cuenta de servicio
        if ($usarGenerativeAI) {
            $endpoint .= "?key={$apiKey}";
        } else {
            // Para autenticación con cuenta de servicio, se requiere OAuth2 token
            $token = self::obtenerTokenGoogle();
            if (empty($token)) {
                \Yii::error('No se pudo obtener token de Google Cloud. Verifique las credenciales configuradas.', 'ia-manager');
                throw new \Exception('Error de autenticación con Google Cloud: No se pudo obtener token OAuth2. Configure google_cloud_credentials_path o google_cloud_api_key en frontend/config/params-local.php');
            }
            $headers['Authorization'] = 'Bearer ' . $token;
        }
        
        // Preparar payload base - ambos APIs usan el mismo formato para Gemini
        // maxOutputTokens: usar configuración específica de Google o un valor alto por defecto
        // Prioridad: google_max_output_tokens > vertex_ai_max_tokens > 8192 (máximo para Gemini)
        $maxOutputTokens = (int)(Yii::$app->params['google_max_output_tokens'] ?? 
                                 Yii::$app->params['vertex_ai_max_tokens'] ?? 
                                 8192);
        // Asegurar que no exceda el máximo permitido por Google (8192 para Gemini)
        // Pero permitir valores más altos si el modelo lo soporta
        $maxOutputTokens = min($maxOutputTokens, 8192);
        
        // Si el valor es muy bajo (menos de 2000), aumentarlo a un mínimo razonable
        // porque respuestas JSON pueden necesitar más tokens
        if ($maxOutputTokens < 2000) {
            $maxOutputTokens = 8192; // Usar máximo por defecto si está configurado muy bajo
            \Yii::warning("IAManager: maxOutputTokens estaba muy bajo, aumentado a 8192 para evitar truncamiento", 'ia-manager');
        }
        
        $payload = [
            'contents' => [], // Se llenará con el prompt
            'generationConfig' => [
                'maxOutputTokens' => $maxOutputTokens,
                'temperature' => (float)(Yii::$app->params['hf_temperature'] ?? 0.3)
            ]
        ];
        
        // Para modelos con reasoning (como Gemini 2.5 Pro), considerar desactivar reasoning
        // para respuestas JSON estructuradas, ya que consume tokens del límite
        // Nota: Esto puede no estar disponible en todos los modelos
        if (strpos($model, '2.5') !== false || strpos($model, '2.0') !== false) {
            // Intentar desactivar reasoning para respuestas más largas
            // Esto puede ayudar a que más tokens estén disponibles para la salida
            if (!isset($payload['generationConfig']['reasoningConfig'])) {
                // Algunos modelos permiten desactivar reasoning o limitarlo
                // Comentado por ahora hasta confirmar soporte del modelo
                // $payload['generationConfig']['reasoningConfig'] = ['enabled' => false];
            }
        }
        
        \Yii::info("IAManager: Configuración Google con maxOutputTokens: {$maxOutputTokens}, Modelo: {$model}", 'ia-manager');
        
        return [
            'tipo' => 'google',
            'endpoint' => $endpoint,
            'headers' => $headers,
            'usar_generative_ai' => $usarGenerativeAI,
            'payload' => $payload
        ];
    }
    
    /**
     * Obtener token de acceso de Google Cloud (OAuth2)
     * @return string
     */
    private static function obtenerTokenGoogle()
    {
        // Si hay una API key, no necesitamos token
        if (!empty(Yii::$app->params['google_cloud_api_key'] ?? '')) {
            return '';
        }
        
        // Ruta al archivo de credenciales (params en common)
        $credentialsPath = Yii::$app->params['google_cloud_credentials_path'] ?? '';
        
        if (empty($credentialsPath) || !file_exists($credentialsPath)) {
            \Yii::warning('Google Cloud credentials no encontradas. Configure google_cloud_credentials_path o google_cloud_api_key en frontend/config/params-local.php', 'ia-manager');
            return '';
        }
        
        // Leer credenciales JSON
        $credentialsJson = file_get_contents($credentialsPath);
        $credentials = json_decode($credentialsJson, true);
        
        if (json_last_error() !== JSON_ERROR_NONE || !isset($credentials['private_key'])) {
            \Yii::error('Error leyendo credenciales de Google Cloud: ' . json_last_error_msg(), 'ia-manager');
            return '';
        }
        
        // Verificar si tenemos un token en cache (válido por 1 hora)
        $cacheKey = 'google_oauth_token_' . md5($credentialsPath);
        $cachedToken = Yii::$app->cache->get($cacheKey);
        if ($cachedToken !== false) {
            return $cachedToken;
        }
        
        // Crear JWT para obtener access token
        $now = time();
        $jwt = self::crearJWTGoogle($credentials, $now);
        
        if (empty($jwt)) {
            \Yii::error('Error creando JWT para Google Cloud', 'ia-manager');
            return '';
        }
        
        // Intercambiar JWT por access token
        $tokenUri = $credentials['token_uri'] ?? 'https://oauth2.googleapis.com/token';
        $client = new Client();
        
        try {
            $response = $client->createRequest()
                ->setMethod('POST')
                ->setUrl($tokenUri)
                ->setContent(http_build_query([
                    'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                    'assertion' => $jwt
                ]))
                ->addHeaders(['Content-Type' => 'application/x-www-form-urlencoded'])
                ->send();
            
            if ($response->isOk) {
                $tokenData = json_decode($response->content, true);
                $accessToken = $tokenData['access_token'] ?? '';
                
                if (!empty($accessToken)) {
                    // Guardar en cache (expira en 50 minutos, los tokens duran 1 hora)
                    $expiresIn = ($tokenData['expires_in'] ?? 3600) - 600; // 10 minutos antes de expirar
                    Yii::$app->cache->set($cacheKey, $accessToken, $expiresIn);
                    return $accessToken;
                }
            } else {
                \Yii::error('Error obteniendo token de Google Cloud: ' . $response->statusCode . ' - ' . $response->content, 'ia-manager');
            }
        } catch (\Exception $e) {
            \Yii::error('Excepción obteniendo token de Google Cloud: ' . $e->getMessage(), 'ia-manager');
        }
        
        return '';
    }
    
    /**
     * Crear JWT para autenticación con Google Cloud
     * @param array $credentials Credenciales de la cuenta de servicio
     * @param int $now Timestamp actual
     * @return string JWT firmado
     */
    private static function crearJWTGoogle($credentials, $now)
    {
        if (!isset($credentials['private_key']) || !isset($credentials['client_email'])) {
            return '';
        }
        
        // Headers del JWT
        $header = [
            'alg' => 'RS256',
            'typ' => 'JWT'
        ];
        
        // Claims del JWT
        $claims = [
            'iss' => $credentials['client_email'],
            'scope' => 'https://www.googleapis.com/auth/cloud-platform',
            'aud' => $credentials['token_uri'] ?? 'https://oauth2.googleapis.com/token',
            'exp' => $now + 3600, // Expira en 1 hora
            'iat' => $now
        ];
        
        // Codificar header y claims en base64url
        $headerEncoded = self::base64UrlEncode(json_encode($header));
        $claimsEncoded = self::base64UrlEncode(json_encode($claims));
        
        // Crear signature
        $signatureInput = $headerEncoded . '.' . $claimsEncoded;
        $privateKey = openssl_pkey_get_private($credentials['private_key']);
        
        if ($privateKey === false) {
            \Yii::error('Error obteniendo clave privada de Google Cloud: ' . openssl_error_string(), 'ia-manager');
            return '';
        }
        
        $signature = '';
        if (!openssl_sign($signatureInput, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
            \Yii::error('Error firmando JWT: ' . openssl_error_string(), 'ia-manager');
            openssl_free_key($privateKey);
            return '';
        }
        
        openssl_free_key($privateKey);
        $signatureEncoded = self::base64UrlEncode($signature);
        
        return $signatureInput . '.' . $signatureEncoded;
    }
    
    /**
     * Codificar en base64url (RFC 4648)
     * @param string $data
     * @return string
     */
    private static function base64UrlEncode($data)
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
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
            // Usar router.huggingface.co con formato compatible OpenAI
            'endpoint' => "https://router.huggingface.co/v1/chat/completions",
            'headers' => [
                'Authorization' => 'Bearer ' . (Yii::$app->params['hf_api_key'] ?? ''),
                'Content-Type' => 'application/json'
            ],
            'payload' => [
                'model' => $modelo,
                'messages' => [], // Se llenará con el prompt
                'stream' => false,
                // Aumentar max_tokens para evitar respuestas JSON truncadas
                'max_tokens' => (int)(Yii::$app->params['hf_max_length'] ?? 2000),
                'temperature' => (float)(Yii::$app->params['hf_temperature'] ?? 0.2) // Más bajo para tareas determinísticas
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
            case 'huggingface':
                // Hugging Face usa el mismo formato que OpenAI/Groq
                $proveedorIA['payload']['messages'][] = ['role' => 'user', 'content' => $prompt];
                break;
            case 'google':
                // Google Generative AI API y Vertex AI usan el mismo formato 'contents' con 'parts'
                $proveedorIA['payload']['contents'][] = [
                    'role' => 'user',
                    'parts' => [
                        ['text' => $prompt]
                    ]
                ];
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
        // Log de la respuesta original antes de procesar
        $responseContent = $response->content ?? '';
        $responseContentLength = strlen($responseContent);
        \Yii::info("IAManager::procesarRespuestaProveedorInstance - INICIO. Tipo: {$tipo}. Respuesta original (longitud: {$responseContentLength}): {$responseContent}", 'ia-manager');
        
        // Descomprimir respuesta si está comprimida
        $content = self::descomprimirRespuesta($response);
        
        // Log del contenido después de descomprimir
        \Yii::info("IAManager::procesarRespuestaProveedorInstance - Después de descomprimir. Contenido (longitud: " . strlen($content) . "): {$content}", 'ia-manager');
        
        $responseData = json_decode($content, true);
        
        // Log del JSON parseado
        \Yii::info("IAManager::procesarRespuestaProveedorInstance - JSON parseado. JSON Error: " . json_last_error_msg() . ". ResponseData completo: " . json_encode($responseData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), 'ia-manager');
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            \Yii::error('Error decodificando JSON de IA: ' . json_last_error_msg() . ' - Contenido preview: ' . substr($content, 0, 200), 'ia-manager');
            return null;
        }

        $contenido = null;
        switch ($tipo) {
            case 'ollama':
                $contenido = $responseData['response'] ?? null;
                break;
            case 'openai':
            case 'groq':
            case 'huggingface':
                // Hugging Face usa el mismo formato de respuesta que OpenAI/Groq
                $contenido = $responseData['choices'][0]['message']['content'] ?? null;
                break;
            case 'google':
                // Google Vertex AI/Gemini puede usar diferentes formatos
                // Formato Generative AI API (candidates) - más común
                if (isset($responseData['candidates'][0]['content']['parts'])) {
                    $parts = $responseData['candidates'][0]['content']['parts'];
                    $numParts = count($parts);
                    \Yii::info("IAManager: Google respuesta con {$numParts} partes", 'ia-manager');
                    
                    // Concatenar todas las partes (Google puede dividir respuestas largas en múltiples partes)
                    $contenido = '';
                    foreach ($parts as $index => $part) {
                        if (isset($part['text'])) {
                            $contenido .= $part['text'];
                            \Yii::info("IAManager: Parte {$index} longitud: " . strlen($part['text']), 'ia-manager');
                        }
                    }
                    
                    if (!empty($contenido)) {
                        \Yii::info("IAManager: Contenido total concatenado longitud: " . strlen($contenido), 'ia-manager');
                    }
                }
                // Formato nativo Vertex AI (predictions) - para endpoint predict
                elseif (isset($responseData['predictions'][0]['content'])) {
                    $contenido = $responseData['predictions'][0]['content'];
                }
                // Formato Vertex AI con estructura de texto
                elseif (isset($responseData['predictions'][0]['text'])) {
                    $contenido = $responseData['predictions'][0]['text'];
                }
                // Formato compatible OpenAI (si se usa endpoint de chat completions)
                elseif (isset($responseData['choices'][0]['message']['content'])) {
                    $contenido = $responseData['choices'][0]['message']['content'];
                }
                break;
            default:
                $contenido = $responseData;
        }

        // Log del contenido final extraído
        $contenidoLength = is_string($contenido) ? strlen($contenido) : (is_array($contenido) ? 'array con ' . count($contenido) . ' elementos' : gettype($contenido));
        $contenidoPreview = is_string($contenido) ? $contenido : (is_array($contenido) ? json_encode($contenido, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) : (string)$contenido);
        \Yii::info("IAManager::procesarRespuestaProveedorInstance - Contenido final extraído (tipo: {$tipo}, longitud/tamaño: {$contenidoLength}): {$contenidoPreview}", 'ia-manager');

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
            // Validación previa: prompt vacío o muy corto
            $prompt = trim($prompt);
            if (empty($prompt) || strlen($prompt) < 3) {
                \Yii::warning("Prompt vacío o muy corto, saltando request de IA", 'ia-manager');
                if (class_exists(\common\components\Ai\Cost\AICostTracker::class)) {
                    \common\components\Ai\Cost\AICostTracker::registrarEvitada('validacion', $contexto);
                }
                return null;
            }
            
            // Verificar deduplicación primero
            $deduplicado = \common\components\RequestDeduplicator::buscarSimilar($prompt, $contexto);
            if ($deduplicado !== null) {
                \Yii::info("Request duplicado encontrado para: {$contexto}", 'ia-manager');
                if (class_exists(\common\components\Ai\Cost\AICostTracker::class)) {
                    \common\components\Ai\Cost\AICostTracker::registrarEvitada('dedup', $contexto);
                }
                return $deduplicado;
            }
            
            // Verificar cache (solo si no está desactivado)
            $cacheDesactivado = Yii::$app->params['ia_cache_desactivado'] ?? false;
            $cacheKey = 'ia_response_' . md5($prompt . $contexto . $tipoModelo);
            $yiiCache = Yii::$app->cache;
            
            if ($cacheDesactivado) {
                \Yii::info("⚠️ ESTRUCTURACIÓN: Cache DESACTIVADO - Forzando llamada a IA (contexto: {$contexto})", 'ia-manager');
            } elseif ($yiiCache) {
                $cached = $yiiCache->get($cacheKey);
                if ($cached !== false) {
                    \Yii::info("✅ ESTRUCTURACIÓN: Obtenida desde CACHE para contexto: {$contexto}", 'ia-manager');
                    if ($logger) {
                        $logger->registrar(
                            'CACHE',
                            'Análisis obtenido desde cache',
                            'No se realizó llamada a IA',
                            [
                                'metodo' => 'IAManager::consultarIA',
                                'fuente' => 'cache',
                                'contexto' => $contexto,
                                'tipo_modelo' => $tipoModelo
                            ]
                        );
                    }
                    // Guardar en deduplicador también
                    \common\components\RequestDeduplicator::guardar($prompt, $cached, $contexto);
                    if (class_exists(\common\components\Ai\Cost\AICostTracker::class)) {
                        \common\components\Ai\Cost\AICostTracker::registrarEvitada('cache', $contexto);
                    }
                    return $cached;
                }
            }
            
            // No está en cache, hacer llamada real a la IA
            \Yii::info("🔄 ESTRUCTURACIÓN: Realizando llamada a IA para contexto: {$contexto}", 'ia-manager');
            if ($logger) {
                $logger->registrar(
                    'IA',
                    'Realizando análisis con IA',
                    'Llamada a proveedor de IA',
                    [
                        'metodo' => 'IAManager::consultarIA',
                        'fuente' => 'ia',
                        'contexto' => $contexto,
                        'tipo_modelo' => $tipoModelo,
                        'prompt_preview' => substr($prompt, 0, 100)
                    ]
                );
            }
            
            // Verificar rate limiter
            $endpoint = '';
            $proveedorIA = self::getProveedorIA();
            
            // Si es HuggingFace, usar el tipo de modelo específico
            if ($proveedorIA['tipo'] === 'huggingface') {
                $proveedorIA = self::getConfiguracionHuggingFace($tipoModelo);
                $endpoint = $proveedorIA['endpoint'];
                
                // Optimización: Ajustar max_length dinámicamente según longitud del prompt
                $longitudPrompt = strlen($prompt);
                $maxLengthBase = (int)(Yii::$app->params['hf_max_length'] ?? 500);
                
                // Reducir max_length para prompts cortos (respuestas más cortas = menos tokens)
                if ($longitudPrompt < 100) {
                    $maxLengthOptimizado = min(300, $maxLengthBase);
                } elseif ($longitudPrompt < 200) {
                    $maxLengthOptimizado = min(400, $maxLengthBase);
                } else {
                    $maxLengthOptimizado = $maxLengthBase;
                }
                
                if (isset($proveedorIA['payload']['parameters'])) {
                    $proveedorIA['payload']['parameters']['max_length'] = $maxLengthOptimizado;
                } elseif (isset($proveedorIA['payload']['options'])) {
                    $proveedorIA['payload']['options']['max_length'] = $maxLengthOptimizado;
                }
                
                // Registrar uso del modelo para gestión de memoria
                $modeloId = self::extraerModeloId($endpoint);
                ModelManager::registrarUso($modeloId, $tipoModelo);
                
                // Verificar si el modelo debe estar cargado
                if (!ModelManager::debeEstarCargado($modeloId, $tipoModelo)) {
                    \Yii::warning("Modelo no disponible en memoria: {$modeloId}. Cargando...", 'ia-manager');
                    // En un sistema real, aquí se cargaría el modelo
                }
                
                // Verificar rate limiter
                if (!HuggingFaceRateLimiter::puedeHacerRequest($endpoint, false)) {
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

            // Preparar payload JSON
            $payloadJson = json_encode($proveedorIA['payload']);
            
            // Validar JSON antes de enviar
            if (json_last_error() !== JSON_ERROR_NONE) {
                \Yii::error("Error codificando JSON para IA: " . json_last_error_msg() . " - Payload: " . print_r($proveedorIA['payload'], true), 'ia-manager');
                return null;
            }
            
            // Comprimir datos en tránsito (gzip) para reducir ancho de banda
            // Solo para proveedores que lo aceptan (HuggingFace)
            // NOTA: La nueva API router.huggingface.co podría no aceptar compresión gzip
            $compresion = self::comprimirDatos($payloadJson, $proveedorIA['tipo'] ?? null);
            $headersConCompresion = array_merge($proveedorIA['headers'], $compresion['headers']);
            
            // Log del payload para debugging (solo primeros 500 caracteres)
            \Yii::info("Enviando request a: {$proveedorIA['endpoint']} - Payload preview: " . substr($payloadJson, 0, 500), 'ia-manager');
            
            // Simulación en pruebas de costos: no enviar HTTP, devolver mock
            if (class_exists(\common\components\Ai\Cost\AICostTracker::class) && \common\components\Ai\Cost\AICostTracker::debeSimularIA()) {
                \common\components\Ai\Cost\AICostTracker::registrarLlamadaSimulada($contexto, $tipoModelo);
                return [];
            }
            
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
                        HuggingFaceRateLimiter::registrarExito($endpoint);
                    }
                    
                    // Guardar en cache y deduplicador si es válido (solo si el cache no está desactivado)
                    if ($resultado) {
                        $cacheDesactivado = Yii::$app->params['ia_cache_desactivado'] ?? false;
                        if (!$cacheDesactivado && $yiiCache) {
                            $ttl = (int)(Yii::$app->params['ia_cache_ttl'] ?? 3600);
                            $yiiCache->set($cacheKey, $resultado, $ttl);
                            \Yii::info("💾 ESTRUCTURACIÓN: Guardada en CACHE (TTL: {$ttl}s, contexto: {$contexto})", 'ia-manager');
                        } elseif ($cacheDesactivado) {
                            \Yii::info("⚠️ ESTRUCTURACIÓN: Cache DESACTIVADO - No se guardó en cache", 'ia-manager');
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
                    HuggingFaceRateLimiter::registrarError($endpoint, $response->getStatusCode());
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
                if (class_exists(\common\components\Ai\Cost\AICostTracker::class)) {
                    \common\components\Ai\Cost\AICostTracker::registrarEvitada('cpu', 'corregirPalabra');
                }
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
            if (class_exists(\common\components\Ai\Cost\AICostTracker::class) && \common\components\Ai\Cost\AICostTracker::debeSimularIA()) {
                \common\components\Ai\Cost\AICostTracker::registrarLlamadaSimulada('corregirPalabraLLM', 'text-correction');
                return null;
            }
            // Mejor prompt que enfatiza el contexto
            $prompt = "Eres un especialista médico en {$especialidad}. Analiza la siguiente oración médica y corrige SOLO la palabra indicada si tiene un error ortográfico. Si la palabra está correcta en el contexto, no la cambies.\n\n";
            $prompt .= "Oración: {$contexto}\n";
            $prompt .= "Palabra a revisar: {$palabra}\n";
            $prompt .= "Instrucciones:\n";
            $prompt .= "- Si la palabra tiene un error ortográfico, responde SOLO con la palabra corregida\n";
            $prompt .= "- Si la palabra está correcta (incluyendo preposiciones, artículos, etc.), responde con la misma palabra\n";
            $prompt .= "- Considera el contexto médico de la oración\n";
            $prompt .= "Corrección:";

            $endpoint = \Yii::$app->params['hf_endpoint'] ?? 'https://router.huggingface.co/hf-inference/PlanTL-GOB-ES/roberta-base-biomedical-clinical-es';
            $apiKey = \Yii::$app->params['hf_api_key'] ?? '';

            $payload = [
                'inputs' => $prompt,
                'parameters' => [
                    'max_length' => 50,
                    'temperature' => 0.1,
                    'return_full_text' => false
                ]
            ];

            // Comprimir datos en tránsito (solo para HuggingFace)
            $payloadJson = json_encode($payload);
            $compresion = self::comprimirDatos($payloadJson, 'huggingface');
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
                $content = self::descomprimirRespuesta($response);
                $data = json_decode($content, true);
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
            $prompt = "Corrige SOLO errores ortográficos en las palabras indicadas. NO agregues texto.\n\n";
            $prompt .= "Texto: {$contexto}\n";
            $prompt .= "Reglas estrictas:\n";
            $prompt .= "- Si la palabra tiene error ortográfico, escribe: palabra_original -> palabra_corregida\n";
            $prompt .= "Correcciones:\n";

            $endpoint = \Yii::$app->params['hf_endpoint'] ?? 'https://router.huggingface.co/hf-inference/PlanTL-GOB-ES/roberta-base-biomedical-clinical-es';
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

            // Comprimir datos en tránsito (solo para HuggingFace)
            $payloadJson = json_encode($payload);
            $compresion = self::comprimirDatos($payloadJson, 'huggingface');
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
                $content = self::descomprimirRespuesta($response);
                $data = json_decode($content, true);
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
     * Corregir texto médico completo usando modelo de IA
     * NOTA: Ollama no está disponible (requiere infraestructura local)
     * Usa el proveedor configurado (HuggingFace por defecto)
     * @param string $texto Texto original a corregir
     * @param string|null $especialidad Especialidad médica
     * @return array ['texto_corregido' => string, 'cambios' => array, 'confidence' => float]
     */
    public function corregirTextoCompletoConIA($texto, $especialidad = null)
    {
        $logger = ConsultaLogger::obtenerInstancia();
        $inicio = microtime(true);
        
        try {
            // Verificar cache primero (solo si no está desactivado)
            $cacheDesactivado = Yii::$app->params['correccion_cache_desactivado'] ?? false;
            $cacheKey = 'correccion_texto_' . md5($texto . ($especialidad ?? ''));
            $yiiCache = Yii::$app->cache;
            
            if ($cacheDesactivado) {
                \Yii::info("⚠️ CORRECCIÓN: Cache DESACTIVADO - Forzando llamada a IA (texto: " . substr($texto, 0, 50) . "...)", 'ia-manager');
            } elseif ($yiiCache) {
                $cached = $yiiCache->get($cacheKey);
                if ($cached !== false && is_array($cached)) {
                    // Validar que los cambios en el cache tengan el formato correcto
                    if (isset($cached['cambios']) && is_array($cached['cambios'])) {
                        $cambiosValidos = [];
                        foreach ($cached['cambios'] as $cambio) {
                            if (is_array($cambio) && isset($cambio['original']) && isset($cambio['corrected'])) {
                                // Asegurar que original y corrected sean strings
                                $cambio['original'] = is_array($cambio['original']) ? implode(' ', $cambio['original']) : (string)$cambio['original'];
                                $cambio['corrected'] = is_array($cambio['corrected']) ? implode(' ', $cambio['corrected']) : (string)$cambio['corrected'];
                                $cambiosValidos[] = $cambio;
                            }
                        }
                        $cached['cambios'] = $cambiosValidos;
                    }
                    \Yii::info("✅ CORRECCIÓN: Obtenida desde CACHE (texto: " . substr($texto, 0, 50) . "...)", 'ia-manager');
                    if (class_exists(\common\components\Ai\Cost\AICostTracker::class)) {
                        \common\components\Ai\Cost\AICostTracker::registrarEvitada('cache', 'corregirTextoCompleto');
                    }
                    if ($logger) {
                        $logger->registrar(
                            'CACHE',
                            'Corrección obtenida desde cache',
                            'No se realizó llamada a IA',
                            [
                                'metodo' => 'IAManager::corregirTextoCompletoConIA',
                                'fuente' => 'cache',
                                'longitud_texto' => strlen($texto),
                                'total_cambios' => count($cached['cambios'] ?? [])
                            ]
                        );
                    }
                    return $cached;
                }
            }
            
            // No está en cache, hacer llamada real a la IA
            \Yii::info("🔄 CORRECCIÓN: Realizando llamada a IA (texto: " . substr($texto, 0, 50) . "...)", 'ia-manager');
            if ($logger) {
                $logger->registrar(
                    'IA',
                    'Realizando corrección con IA',
                    'Llamada a proveedor de IA',
                    [
                        'metodo' => 'IAManager::corregirTextoCompletoConIA',
                        'fuente' => 'ia',
                        'longitud_texto' => strlen($texto),
                        'especialidad' => $especialidad
                    ]
                );
            }
            
            // Obtener configuración del proveedor con modelo específico para corrección
            // Usar 'text-correction' para obtener el modelo optimizado para corrección ortográfica
            $proveedorIA = $this->getProveedorIAInstance('text-correction');
            
            // Prompt optimizado para SOLO corrección ortográfica (sin expansión de abreviaturas)
            $prompt = "Corrige y mejora el texto médico manteniendo el significado exacto.

Tareas permitidas:
1. Corregir errores ortográficos (ej: laseracion→laceración, isocorica→isocórica)
2. Expandir abreviaturas médicas comunes cuando mejore la claridad:
   - h → horizontal (cuando se refiere a posición)
   - aprox. → aproximadamente
   - para central → paracentral (cuando tiene sentido médico)
   - OI → ojo izquierdo, OD → ojo derecho
   - Bmc → biomicroscopía
   - Caf → cámara anterior
3. Mejorar puntuación y estructura cuando sea necesario para claridad médica

Reglas importantes:
- MANTÉN el significado médico exacto
- NO agregues información que no esté implícita en el texto original
- NO cambies términos médicos técnicos correctos
- Puedes ajustar el orden de palabras SOLO si mejora la claridad médica sin cambiar el significado
- Devuelve solo el texto corregido, sin ningún otro texto ni explicación.

Texto: {$texto}";

            // Asignar prompt según el tipo de proveedor
            $this->asignarPromptAConfiguracionInstance($proveedorIA, $prompt);
            
            // Registrar en logger si está disponible
            if ($logger) {
                $logger->registrar(
                    'PROCESAMIENTO',
                    'Enviando texto completo para corrección con IA',
                    'Procesando texto completo con modelo de IA',
                    [
                        'metodo' => 'IAManager::corregirTextoCompletoConIA',
                        'proveedor' => $proveedorIA['tipo'] ?? 'huggingface',
                        'modelo' => $proveedorIA['payload']['model'] ?? $proveedorIA['payload']['inputs'] ?? 'desconocido',
                        'longitud_texto' => strlen($texto),
                        'especialidad' => $especialidad
                    ]
                );
            }
            
            // Fallback: Registrar también en log de Yii para asegurar que siempre se vea
            \Yii::info(
                sprintf(
                    'IAManager::corregirTextoCompletoConIA - Proveedor: %s, Modelo: %s, Longitud: %d, Especialidad: %s',
                    $proveedorIA['tipo'] ?? 'huggingface',
                    $proveedorIA['payload']['model'] ?? $proveedorIA['payload']['inputs'] ?? 'desconocido',
                    strlen($texto),
                    $especialidad ?? 'N/A'
                ),
                'ia-manager'
            );
            
            // Simulación en pruebas de costos: no enviar HTTP, devolver mock
            if (class_exists(\common\components\Ai\Cost\AICostTracker::class) && \common\components\Ai\Cost\AICostTracker::debeSimularIA()) {
                \common\components\Ai\Cost\AICostTracker::registrarLlamadaSimulada('corregirTextoCompleto', 'text-correction');
                return [
                    'texto_corregido' => $texto,
                    'cambios' => [],
                    'confidence' => 0,
                    'total_changes' => 0,
                    'processing_time' => microtime(true) - $inicio,
                    'metodo' => 'mock'
                ];
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
                    
                    // CRÍTICO: Filtrar contenido de reasoning de modelos como DeepSeek-R1
                    // Guardar respuesta original para logging ANTES de cualquier filtrado
                    $respuestaOriginal = $textoCorregido;
                    
                    // Log de la respuesta cruda para debugging
                    \Yii::info(
                        "Respuesta cruda de IA (antes de filtrado): " . 
                        substr($respuestaOriginal, 0, 500) . 
                        (strlen($respuestaOriginal) > 500 ? "..." : "") . 
                        " (Total: " . strlen($respuestaOriginal) . " chars)",
                        'ia-manager'
                    );
                    
                    // Eliminar tags de reasoning completos (con contenido entre tags)
                    $textoCorregido = preg_replace('/<think>.*?<\/think>/is', '', $textoCorregido);
                    $textoCorregido = preg_replace('/<think>.*?<\/redacted_reasoning>/is', '', $textoCorregido);
                    $textoCorregido = preg_replace('/<think>.*?<\/redacted_reasoning>/is', '', $textoCorregido);
                    $textoCorregido = preg_replace('/<reasoning>.*?<\/reasoning>/is', '', $textoCorregido);
                    
                    // Eliminar tags sueltos (sin cierre)
                    $textoCorregido = preg_replace('/<think[^>]*>/i', '', $textoCorregido);
                    $textoCorregido = preg_replace('/<redacted_reasoning[^>]*>/i', '', $textoCorregido);
                    $textoCorregido = preg_replace('/<reasoning[^>]*>/i', '', $textoCorregido);
                    $textoCorregido = preg_replace('/<\/think>/i', '', $textoCorregido);
                    $textoCorregido = preg_replace('/<\/redacted_reasoning>/i', '', $textoCorregido);
                    $textoCorregido = preg_replace('/<\/reasoning>/i', '', $textoCorregido);
                    
                    // Eliminar líneas que contengan instrucciones o reasoning
                    $lineas = explode("\n", $textoCorregido);
                    $lineasLimpias = [];
                    foreach ($lineas as $linea) {
                        $lineaLimpia = trim($linea);
                        
                        // Saltar líneas vacías
                        if (empty($lineaLimpia)) {
                            continue;
                        }
                        
                        // Omitir líneas que sean instrucciones o reasoning
                        if (preg_match('/^(Vale|El usuario|Las reglas|debo|Debo|Las son|son estrictas|únicamente|Solo|solo|corregir|Corregir|ortográficos|ortograficos|me pide|debo cambiar)/i', $lineaLimpia)) {
                            continue;
                        }
                        
                        // Omitir líneas que contengan reasoning tags
                        if (preg_match('/<(think|reasoning|redacted_reasoning)/i', $lineaLimpia)) {
                            continue;
                        }
                        
                        // Omitir líneas que solo contengan "Corregido:" o variaciones
                        if (preg_match('/^(Corregido|Texto corregido|Corrección):?\s*$/i', $lineaLimpia)) {
                            continue;
                        }
                        
                        // Omitir líneas que sean claramente instrucciones
                        if (preg_match('/(Tareas permitidas|Reglas importantes|MANTÉN el|NO agregues|NO cambies)/i', $lineaLimpia)) {
                            continue;
                        }
                        
                        $lineasLimpias[] = $linea;
                    }
                    $textoCorregido = implode("\n", $lineasLimpias);
                    
                    // Log si se filtró contenido significativo
                    if (strlen($respuestaOriginal) > strlen($textoCorregido) + 50) {
                        \Yii::info(
                            "Contenido de reasoning filtrado: " . 
                            (strlen($respuestaOriginal) - strlen($textoCorregido)) . " caracteres eliminados. " .
                            "Original: " . substr($respuestaOriginal, 0, 200) . "...",
                            'ia-manager'
                        );
                    }
                    
                    // Limpiar posibles prefijos que el modelo pueda agregar al inicio
                    $textoCorregido = preg_replace('/^(Texto corregido|Corrección|Corregido):\s*/i', '', $textoCorregido);
                    
                    // Limpiar posibles sufijos que el modelo pueda agregar al final
                    $textoCorregido = preg_replace('/\s*(Texto corregido|Corrección|Corregido):?\s*$/i', '', $textoCorregido);
                    
                    $textoCorregido = trim($textoCorregido);
                    
                    // VALIDACIÓN CRÍTICA: Si el texto corregido parece ser instrucciones en lugar de texto médico,
                    // rechazar la respuesta y usar el texto original
                    if (self::esRespuestaInvalida($textoCorregido, $texto)) {
                        // Log detallado de la respuesta completa para debugging
                        \Yii::warning(
                            "La IA devolvió una respuesta inválida (parece ser instrucciones). " .
                            "Respuesta completa (" . strlen($textoCorregido) . " chars): " . 
                            substr($textoCorregido, 0, 500),
                            'ia-manager'
                        );
                        if ($logger) {
                            $logger->registrar(
                                'ERROR',
                                'Respuesta IA inválida detectada',
                                'La respuesta parece contener instrucciones en lugar de texto corregido',
                                [
                                    'metodo' => 'IAManager::corregirTextoCompletoConIA',
                                    'respuesta_completa' => $textoCorregido,
                                    'longitud_respuesta' => strlen($textoCorregido),
                                    'longitud_original' => strlen($texto),
                                    'primeras_lineas' => array_slice(explode("\n", $textoCorregido), 0, 5)
                                ]
                            );
                        }
                        // Retornar texto original sin cambios
                        return [
                            'texto_corregido' => $texto,
                            'cambios' => [],
                            'confidence' => 0.0,
                            'total_changes' => 0,
                            'processing_time' => microtime(true) - $inicio,
                            'metodo' => 'ia_local',
                            'error' => 'respuesta_invalida'
                        ];
                    }
                    
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
                    
                    // Guardar en cache (solo si el cache no está desactivado)
                    $cacheDesactivado = Yii::$app->params['correccion_cache_desactivado'] ?? false;
                    if (!$cacheDesactivado && $yiiCache) {
                        $ttl = (int)(Yii::$app->params['correccion_cache_ttl'] ?? 7200); // 2 horas para correcciones
                        $yiiCache->set($cacheKey, $resultado, $ttl);
                        \Yii::info("💾 CORRECCIÓN: Guardada en CACHE (TTL: {$ttl}s, cambios: " . count($cambios) . ")", 'ia-manager');
                    } elseif ($cacheDesactivado) {
                        \Yii::info("⚠️ CORRECCIÓN: Cache DESACTIVADO - No se guardó en cache", 'ia-manager');
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
                // Asegurar que sean strings válidos
                $palabraOriginalStr = is_array($palabraOriginal) ? implode(' ', $palabraOriginal) : (string)$palabraOriginal;
                $palabraCorregidaStr = is_array($palabraCorregida) ? implode(' ', $palabraCorregida) : (string)$palabraCorregida;
                
                // Limpiar caracteres problemáticos
                $palabraOriginalStr = trim($palabraOriginalStr);
                $palabraCorregidaStr = trim($palabraCorregidaStr);
                
                // Validar que no estén vacíos después de limpiar
                if (empty($palabraOriginalStr) || empty($palabraCorregidaStr)) {
                    continue;
                }
                
                // Calcular confianza individual para cada cambio
                $confidence = self::calcularConfianzaCambioIndividual($originalLimpia, $corregidaLimpia);
                
                $cambios[] = [
                    'original' => $palabraOriginalStr,
                    'corrected' => $palabraCorregidaStr,
                    'confidence' => $confidence,
                    'method' => 'ia_local'
                ];
            }
        }
        
        return $cambios;
    }

    /**
     * Calcular confianza individual para un cambio específico
     * Si la IA corrigió el texto completo, asumimos 100% de confianza para cada cambio
     * 
     * @param string $original
     * @param string $corrected
     * @return float
     */
    private function calcularConfianzaCambioIndividual($original, $corrected)
    {
        // Si la IA corrigió el texto completo, confiamos 100% en cada cambio
        // La IA tiene contexto completo del texto médico
        return 1.0;
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
        // Usar regex más robusto que maneje JSON anidado
        if (preg_match('/\{[^{}]*(?:\{[^{}]*\}[^{}]*)*\}/s', $respuesta, $matches)) {
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

        // Intentar reparar JSON truncado (cerrar strings y objetos abiertos)
        $respuestaReparada = $this->intentarRepararJSONTruncado($respuesta);
        if ($respuestaReparada) {
            $jsonData = json_decode($respuestaReparada, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                \Yii::warning('JSON reparado exitosamente después de estar truncado', 'ia-manager');
                return $jsonData;
            }
        }

        // Log completo de la respuesta para debugging (más de 200 caracteres)
        $respuestaPreview = strlen($respuesta) > 500 ? substr($respuesta, 0, 500) . '...' : $respuesta;
        \Yii::error('No se pudo decodificar JSON de la IA: ' . json_last_error_msg() . ' - Respuesta (' . strlen($respuesta) . ' chars): ' . $respuestaPreview, 'ia-manager');
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
     * Intentar reparar JSON truncado
     * @param string $json
     * @return string|null JSON reparado o null si no se pudo reparar
     */
    private function intentarRepararJSONTruncado($json)
    {
        // Contar llaves abiertas y cerradas
        $abiertas = substr_count($json, '{');
        $cerradas = substr_count($json, '}');
        $abiertasCorchetes = substr_count($json, '[');
        $cerradasCorchetes = substr_count($json, ']');
        
        // Si hay más llaves abiertas que cerradas, intentar cerrarlas
        if ($abiertas > $cerradas) {
            $json .= str_repeat('}', $abiertas - $cerradas);
        }
        
        // Si hay más corchetes abiertos que cerrados, intentar cerrarlos
        if ($abiertasCorchetes > $cerradasCorchetes) {
            $json .= str_repeat(']', $abiertasCorchetes - $cerradasCorchetes);
        }
        
        // Buscar strings sin cerrar (comillas dobles sin pareja)
        // Esto es más complejo, pero podemos intentar cerrar el último string abierto
        $ultimaComilla = strrpos($json, '"');
        if ($ultimaComilla !== false) {
            // Contar comillas antes de la última
            $comillasAntes = substr_count(substr($json, 0, $ultimaComilla), '"');
            // Si hay un número impar de comillas, la última está abierta
            if ($comillasAntes % 2 === 0) {
                // La última comilla está abierta, cerrarla
                $json .= '"';
            }
        }
        
        // Validar que el JSON reparado sea válido
        $test = json_decode($json, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $json;
        }
        
        return null;
    }

    /**
     * Validar si una respuesta de la IA es inválida (contiene instrucciones en lugar de texto corregido)
     * @param string $respuesta
     * @param string $textoOriginal
     * @return bool true si la respuesta es inválida
     */
    private static function esRespuestaInvalida($respuesta, $textoOriginal)
    {
        // Si la respuesta está vacía, es inválida
        if (empty(trim($respuesta))) {
            return true;
        }
        
        // Si la respuesta contiene tags de reasoning que no fueron filtrados, es inválida
        if (preg_match('/<(think|reasoning|redacted_reasoning)/i', $respuesta)) {
            return true;
        }
        
        // Detectar si la respuesta parece ser instrucciones en lugar de texto médico
        // Solo revisar las primeras líneas para evitar falsos positivos
        $primerasLineas = explode("\n", $respuesta);
        $primerasLineas = array_slice($primerasLineas, 0, 2); // Revisar solo primeras 2 líneas
        
        $contadorInstrucciones = 0;
        foreach ($primerasLineas as $linea) {
            $lineaLimpia = trim($linea);
            if (empty($lineaLimpia)) {
                continue;
            }
            
            // Patrones más específicos que indican claramente instrucciones
            $patronesInstrucciones = [
                '/^(Vale|El usuario me pide|Las reglas son|debo|Debo|Las son estrictas|son estrictas:|únicamente|Solo debo|solo corregir|Corregir ortográficos|ortograficos)/i',
                '/me pide corregir/i',
                '/debo cambiar únicamente/i',
                '/las reglas son estrictas/i',
                '/Tareas permitidas:/i',
                '/Reglas importantes:/i',
                '/MANTÉN el significado/i',
                '/NO agregues información/i',
                '/NO cambies términos/i'
            ];
            
            foreach ($patronesInstrucciones as $patron) {
                if (preg_match($patron, $lineaLimpia)) {
                    $contadorInstrucciones++;
                }
            }
        }
        
        // Solo considerar inválida si hay múltiples indicadores de instrucciones
        // Y la respuesta es muy corta (menos del 50% del original)
        if ($contadorInstrucciones >= 2) {
            $longitudRespuesta = strlen($respuesta);
            $longitudOriginal = strlen($textoOriginal);
            
            // Si la respuesta es muy corta comparada con el original, probablemente son solo instrucciones
            if ($longitudOriginal > 0 && ($longitudRespuesta / $longitudOriginal) < 0.5) {
                return true;
            }
        }
        
        // Si la respuesta es muy diferente en longitud (más del 200% de diferencia), puede ser inválida
        // Pero solo si también contiene palabras de instrucciones
        $diferenciaLongitud = abs(strlen($respuesta) - strlen($textoOriginal));
        $porcentajeDiferencia = strlen($textoOriginal) > 0 
            ? ($diferenciaLongitud / strlen($textoOriginal)) * 100 
            : 0;
        
        // Si la diferencia es muy grande Y la respuesta contiene palabras de instrucciones, es inválida
        if ($porcentajeDiferencia > 50) {
            $palabrasInstrucciones = ['usuario', 'reglas', 'debo', 'debes', 'debe', 'corregir', 'ortográficos', 'estrictas'];
            foreach ($palabrasInstrucciones as $palabra) {
                if (stripos($respuesta, $palabra) !== false) {
                    return true;
                }
            }
        }
        
        return false;
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
