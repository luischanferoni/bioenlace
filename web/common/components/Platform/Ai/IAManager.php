<?php

namespace common\components\Platform\Ai;

use Yii;
use common\components\Platform\Core\Db\BioenlaceDb;
use yii\httpclient\Client;
use common\components\Platform\Ai\HuggingFace\HuggingFaceRateLimiter;
use common\components\Shared\Infrastructure\Requests\RequestDeduplicator;
use common\components\Platform\Ai\Providers\Google\GoogleAuth;
use common\components\Platform\Ai\Providers\Google\GoogleCloudConfigResolver;

/**
 * Fachada de interacciones con LLMs (proveedores, caché, telemetría).
 *
 * Hooks de rubro (logger clínico, boost de confianza, prompt SNOMED) se resuelven
 * por FQCN opcional para no acoplar Platform a Domain en compile-time.
 */
class IAManager
{
    /**
     * Logger clínico del rubro si está cargado (Domain); null en plataforma pura.
     */
    private static function clinicalLogger(): ?object
    {
        $class = 'common\\components\\Domain\\Clinical\\Capture\\Infrastructure\\Logging\\EncounterCaptureLogger';
        if (!class_exists($class)) {
            return null;
        }

        return $class::obtenerInstancia();
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
        $projectId = GoogleCloudConfigResolver::projectId();
        $location = GoogleCloudConfigResolver::region();
        $model = Yii::$app->params['vertex_ai_model'] ?? 'gemini-2.5-flash-lite';
        
        $apiKey = GoogleCloudConfigResolver::apiKey();
        $usarGenerativeAI = $apiKey !== '';
        
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
            $token = GoogleAuth::getAccessToken();
            if ($token === '') {
                \Yii::error('No se pudo obtener token de Google Cloud. Verifique las credenciales configuradas.', 'ia-manager');
                throw new \Exception(
                    'Error de autenticación con Google Cloud: No se pudo obtener token OAuth2. Configure google_cloud_credentials_path o google_cloud_api_key en '
                    . GoogleCloudConfigResolver::PARAMS_HINT
                );
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
        $logger = self::clinicalLogger();
        
        try {
            // Validación previa: prompt vacío o muy corto
            $prompt = trim($prompt);
            if (empty($prompt) || strlen($prompt) < 3) {
                \Yii::warning("Prompt vacío o muy corto, saltando request de IA", 'ia-manager');
                if (class_exists(\common\components\Platform\Ai\Cost\AICostTracker::class)) {
                    \common\components\Platform\Ai\Cost\AICostTracker::registrarEvitada('validacion', $contexto);
                }
                return null;
            }
            
            // Caché desactivado: se ignora tanto el caché de Yii como la deduplicación.
            $cacheDesactivado = Yii::$app->params['ia_cache_desactivado'] ?? false;

            // Deduplicación exacta: mismo prompt (normalizado) + contexto, ya resuelto en este request.
            if (!$cacheDesactivado) {
                $deduplicado = RequestDeduplicator::buscarExacto($prompt, $contexto);
                if ($deduplicado !== null) {
                    \Yii::info("Request duplicado encontrado para: {$contexto}", 'ia-manager');
                    if (class_exists(\common\components\Platform\Ai\Cost\AICostTracker::class)) {
                        \common\components\Platform\Ai\Cost\AICostTracker::registrarEvitada('dedup', $contexto);
                    }
                    return $deduplicado;
                }
            }

            // Observabilidad: qué habría hecho un match difuso. No devuelve contenido ni decide nada.
            RequestDeduplicator::observarSimilitud($prompt, $contexto);

            // Verificar cache (solo si no está desactivado)
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
                    RequestDeduplicator::guardar($prompt, $cached, $contexto);
                    if (class_exists(\common\components\Platform\Ai\Cost\AICostTracker::class)) {
                        \common\components\Platform\Ai\Cost\AICostTracker::registrarEvitada('cache', $contexto);
                    }
                    return $cached;
                }
            }
            
            // No está en cache, hacer llamada real a la IA
            \Yii::info("🔄 ESTRUCTURACIÓN: Realizando llamada a IA para contexto: {$contexto}", 'ia-manager');
            
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
                
                // Verificar rate limiter
                if (!HuggingFaceRateLimiter::puedeHacerRequest($endpoint, false)) {
                    \Yii::warning("Rate limit alcanzado para: {$endpoint}", 'ia-manager');
                    return null;
                }
            }
            
            // Asignar el prompt (Google: opcional split estable/variable para context caching simulado)
            $promptAsignado = false;
            if (
                ($proveedorIA['tipo'] ?? '') === 'google'
                && class_exists(\common\components\Platform\Ai\Providers\Google\VertexContextCacheSimulator::class)
            ) {
                $promptAsignado = \common\components\Platform\Ai\Providers\Google\VertexContextCacheSimulator::assignPromptIfApplicable(
                    $proveedorIA,
                    (string) $contexto,
                    $prompt
                );
            }
            if (!$promptAsignado) {
                self::asignarPromptAConfiguracion($proveedorIA, $prompt);
            }
            
            if ($logger) {
                $logger->registrar(
                    'IA',
                    null,
                    'Llamada a proveedor',
                    [
                        'metodo' => 'IAManager::consultarIA',
                        'proveedor' => $proveedorIA['tipo'] ?? 'desconocido',
                    ]
                );
            }
            
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
            if (class_exists(\common\components\Platform\Ai\Cost\AICostTracker::class) && \common\components\Platform\Ai\Cost\AICostTracker::debeSimularIA()) {
                \common\components\Platform\Ai\Cost\AICostTracker::registrarLlamadaSimulada($contexto, $tipoModelo);
                return [];
            }
            
            BioenlaceDb::releaseConnection();
            try {
                $client = new Client();
                $response = $client->createRequest()
                    ->setMethod('POST')
                    ->setUrl($proveedorIA['endpoint'])
                    ->addHeaders($headersConCompresion)
                    ->setContent($compresion['data'])
                    ->send();
            } finally {
                BioenlaceDb::ensureConnection();
            }

            if ($response->isOk) {
                if (
                    ($proveedorIA['tipo'] ?? '') === 'google'
                    && class_exists(\common\components\Platform\Ai\Cost\AICostTracker::class)
                ) {
                    \common\components\Platform\Ai\Cost\AICostTracker::registrarUsoDesdeRespuestaGemini(
                        (string) ($response->content ?? ''),
                        $contexto
                    );
                    if (class_exists(\common\components\Platform\Ai\Providers\Google\VertexContextCacheSimulator::class)) {
                        \common\components\Platform\Ai\Providers\Google\VertexContextCacheSimulator::complementarTracking(
                            (string) ($response->content ?? ''),
                            $contexto,
                            $proveedorIA['_vertex_cache_sim_key'] ?? null
                        );
                    }
                    \common\components\Platform\Ai\Cost\AICostTracker::registrarLlamadaReal($contexto, $tipoModelo);
                }

                // Los logs detallados ya se manejan en EncounterCaptureLogger
                $responseData = self::procesarRespuestaProveedor($response, $proveedorIA['tipo']);
                
                if ($responseData) {
                    // text-generation devuelve prosa (p. ej. asistente-conversational), no JSON estructurado
                    if ($tipoModelo === 'text-generation') {
                        $resultado = is_string($responseData) ? trim($responseData) : null;
                        if ($resultado === '') {
                            $resultado = null;
                        }
                    } else {
                        $resultado = self::validarYLimpiarRespuestaJSON($responseData);
                    }
                    
                    // Registrar éxito en rate limiter
                    if ($proveedorIA['tipo'] === 'huggingface' && !empty($endpoint)) {
                        HuggingFaceRateLimiter::registrarExito($endpoint);
                    }
                    
                    // Guardar en cache y deduplicador si es válido (solo si el cache no está desactivado)
                    if ($resultado) {
                        if (!$cacheDesactivado && $yiiCache) {
                            $ttl = (int)(Yii::$app->params['ia_cache_ttl'] ?? 3600);
                            $yiiCache->set($cacheKey, $resultado, $ttl);
                            \Yii::info("💾 ESTRUCTURACIÓN: Guardada en CACHE (TTL: {$ttl}s, contexto: {$contexto})", 'ia-manager');
                        } elseif ($cacheDesactivado) {
                            \Yii::info("⚠️ ESTRUCTURACIÓN: Cache DESACTIVADO - No se guardó en cache", 'ia-manager');
                        }
                        if (!$cacheDesactivado) {
                            RequestDeduplicator::guardar($prompt, $resultado, $contexto);
                        }
                    }
                    
                    if ($logger) {
                        $logger->registrar(
                            'IA',
                            null,
                            $resultado ? 'OK' : 'Error procesando respuesta',
                            [
                                'metodo' => 'IAManager::consultarIA',
                                'status_code' => $response->getStatusCode(),
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
     *
     * @param string $prompt
     * @param string $contexto
     * @param string $tipoModelo text-generation | text-correction | analysis
     * @return array|string|null
     */
    public function consultar($prompt, $contexto = 'consulta-general', $tipoModelo = 'text-generation')
    {
        return self::consultarIA($prompt, $contexto, $tipoModelo);
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
        $class = 'common\\components\\Domain\\Terminology\\Snomed\\SnomedContextualPromptService';
        if (!class_exists($class)) {
            return $texto;
        }

        return $class::build($texto, $categoria);
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
            // JSON válido - los logs detallados ya se manejan en EncounterCaptureLogger
            return $jsonData;
        }

        // Si falla, intentar limpiar más
        $respuestaLimpia = $this->limpiarRespuestaJSONInstance($respuesta);
        $jsonData = json_decode($respuestaLimpia, true);

        if (json_last_error() === JSON_ERROR_NONE) {
            // JSON válido - los logs detallados ya se manejan en EncounterCaptureLogger
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
