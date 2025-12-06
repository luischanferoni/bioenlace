<?php

namespace common\components;

use Yii;
use yii\httpclient\Client;

/**
 * Componente para manejo de embeddings semánticos
 * Genera vectores numéricos y calcula similitudes para búsqueda semántica real
 */
class EmbeddingsManager
{
    private static $cache = [];
    private const CACHE_TTL = 3600; // 1 hora
    private const SIMILITUD_MINIMA = 0.7; // Umbral mínimo de similitud
    
    /**
     * Generar embedding para un texto usando HuggingFace o OpenAI
     * @param string $texto
     * @param bool $useHuggingFace Si true, usa modelos de HuggingFace (más económico)
     * @return array|null
     */
    public static function generarEmbedding($texto, $useHuggingFace = true)
    {
        // Verificar cache en memoria primero
        $cacheKey = 'embedding_' . md5($texto);
        if (isset(self::$cache[$cacheKey])) {
            return self::$cache[$cacheKey];
        }
        
        // Verificar cache en Yii (FileCache/Redis)
        $yiiCache = Yii::$app->cache;
        if ($yiiCache) {
            $cached = $yiiCache->get($cacheKey);
            if ($cached !== false) {
                // Guardar también en cache de memoria para acceso rápido
                self::$cache[$cacheKey] = $cached;
                return $cached;
            }
        }
        
        try {
            $embedding = null;
            
            if ($useHuggingFace && !empty(Yii::$app->params['hf_api_key'])) {
                // Usar HuggingFace (más económico, especialmente para español)
                $embedding = self::generarEmbeddingHuggingFace($texto);
            }
            
            // Fallback a OpenAI si HuggingFace falla o no está configurado
            if (!$embedding && !empty(Yii::$app->params['openai_api_key'])) {
                $embedding = self::generarEmbeddingOpenAI($texto);
            }
            
            if ($embedding) {
                // Guardar en cache de memoria
                self::$cache[$cacheKey] = $embedding;
                
                // Guardar en cache de Yii (persistente)
                if ($yiiCache) {
                    $yiiCache->set($cacheKey, $embedding, self::CACHE_TTL);
                }
                
                \Yii::info("Embedding generado para: " . substr($texto, 0, 50), 'embeddings');
                return $embedding;
            }
            
        } catch (\Exception $e) {
            \Yii::error("Error en generación de embedding: " . $e->getMessage(), 'embeddings');
        }
        
        return null;
    }
    
    /**
     * Generar embedding usando HuggingFace (más económico)
     * @param string $texto
     * @return array|null
     */
    private static function generarEmbeddingHuggingFace($texto)
    {
        try {
            // Usar modelo de embeddings multilingüe optimizado
            // sentence-transformers/paraphrase-multilingual-MiniLM-L12-v2 es gratuito en Inference API
            $modelo = Yii::$app->params['hf_embedding_model'] ?? 'sentence-transformers/paraphrase-multilingual-MiniLM-L12-v2';
            
            $client = new Client();
            $response = $client->createRequest()
                ->setMethod('POST')
                ->setUrl("https://api-inference.huggingface.co/pipeline/feature-extraction/{$modelo}")
                ->addHeaders([
                    'Authorization' => 'Bearer ' . Yii::$app->params['hf_api_key'],
                    'Content-Type' => 'application/json'
                ])
                ->setContent(json_encode([
                    'inputs' => $texto,
                    'options' => [
                        'wait_for_model' => false // No esperar si el modelo está cargando (evita timeouts costosos)
                    ]
                ]))
                ->send();

            if ($response->isOk) {
                $embedding = json_decode($response->content, true);
                if (is_array($embedding) && !empty($embedding)) {
                    // Si es un array anidado, tomar el primer elemento
                    if (isset($embedding[0]) && is_array($embedding[0])) {
                        $embedding = $embedding[0];
                    }
                    return $embedding;
                }
            } else {
                \Yii::warning('Error generando embedding con HuggingFace: ' . $response->getStatusCode(), 'embeddings');
            }
        } catch (\Exception $e) {
            \Yii::error("Error en generación de embedding HuggingFace: " . $e->getMessage(), 'embeddings');
        }
        
        return null;
    }
    
    /**
     * Generar embedding usando OpenAI (fallback)
     * @param string $texto
     * @return array|null
     */
    private static function generarEmbeddingOpenAI($texto)
    {
        try {
            $client = new Client();
            $response = $client->createRequest()
                ->setMethod('POST')
                ->setUrl('https://api.openai.com/v1/embeddings')
                ->addHeaders([
                    'Authorization' => 'Bearer ' . Yii::$app->params['openai_api_key'],
                    'Content-Type' => 'application/json'
                ])
                ->setContent(json_encode([
                    'model' => 'text-embedding-3-small',
                    'input' => $texto
                ]))
                ->send();

            if ($response->isOk) {
                $responseData = json_decode($response->content, true);
                return $responseData['data'][0]['embedding'] ?? null;
            } else {
                \Yii::error('Error generando embedding OpenAI: ' . $response->getStatusCode(), 'embeddings');
            }
        } catch (\Exception $e) {
            \Yii::error("Error en generación de embedding OpenAI: " . $e->getMessage(), 'embeddings');
        }
        
        return null;
    }
    
    /**
     * Calcular similitud coseno entre dos embeddings
     * @param array $embedding1
     * @param array $embedding2
     * @return float
     */
    public static function calcularSimilitudCoseno($embedding1, $embedding2)
    {
        if (empty($embedding1) || empty($embedding2)) {
            return 0.0;
        }
        
        if (count($embedding1) !== count($embedding2)) {
            \Yii::warning('Embeddings de dimensiones diferentes', 'embeddings');
            return 0.0;
        }
        
        $dotProduct = 0;
        $norm1 = 0;
        $norm2 = 0;
        
        for ($i = 0; $i < count($embedding1); $i++) {
            $dotProduct += $embedding1[$i] * $embedding2[$i];
            $norm1 += $embedding1[$i] * $embedding1[$i];
            $norm2 += $embedding2[$i] * $embedding2[$i];
        }
        
        $denominator = sqrt($norm1) * sqrt($norm2);
        return $denominator > 0 ? $dotProduct / $denominator : 0.0;
    }
    
    /**
     * Encontrar el término más similar usando embeddings
     * @param string $textoUsuario
     * @param array $terminosSnomed
     * @return array|null
     */
    public static function encontrarTerminoSimilar($textoUsuario, $terminosSnomed)
    {
        try {
            // Generar embedding del texto del usuario
            $embeddingUsuario = self::generarEmbedding($textoUsuario);
            if (!$embeddingUsuario) {
                return null;
            }
            
            $mejorMatch = null;
            $mejorSimilitud = 0;
            
            foreach ($terminosSnomed as $termino) {
                $embeddingSnomed = self::generarEmbedding($termino);
                if (!$embeddingSnomed) {
                    continue;
                }
                
                $similitud = self::calcularSimilitudCoseno($embeddingUsuario, $embeddingSnomed);
                
                if ($similitud > $mejorSimilitud) {
                    $mejorSimilitud = $similitud;
                    $mejorMatch = [
                        'termino' => $termino,
                        'similitud' => $similitud,
                        'embedding' => $embeddingSnomed
                    ];
                }
            }
            
            // Solo devolver si supera el umbral mínimo
            if ($mejorSimilitud >= self::SIMILITUD_MINIMA) {
                \Yii::info("Mejor match semántico: {$mejorMatch['termino']} (similitud: {$mejorSimilitud})", 'embeddings');
                return $mejorMatch;
            }
            
            \Yii::info("No se encontró match semántico suficiente (mejor: {$mejorSimilitud})", 'embeddings');
            return null;
            
        } catch (\Exception $e) {
            \Yii::error("Error en búsqueda semántica: " . $e->getMessage(), 'embeddings');
            return null;
        }
    }
    
    /**
     * Generar embeddings en lote para optimizar rendimiento
     * @param array $textos
     * @param bool $useHuggingFace Si true, usa modelos de HuggingFace
     * @return array
     */
    public static function generarEmbeddingsBatch($textos, $useHuggingFace = true)
    {
        $embeddings = [];
        
        // Verificar cache primero para cada texto
        foreach ($textos as $texto) {
            $cacheKey = 'embedding_' . md5($texto);
            $yiiCache = Yii::$app->cache;
            
            if (isset(self::$cache[$cacheKey])) {
                $embeddings[$texto] = self::$cache[$cacheKey];
            } elseif ($yiiCache) {
                $cached = $yiiCache->get($cacheKey);
                if ($cached !== false) {
                    $embeddings[$texto] = $cached;
                    self::$cache[$cacheKey] = $cached;
                }
            }
        }
        
        // Filtrar textos que ya están en cache
        $textosSinCache = array_filter($textos, function($texto) use ($embeddings) {
            return !isset($embeddings[$texto]);
        });
        
        if (empty($textosSinCache)) {
            return $embeddings;
        }
        
        try {
            if ($useHuggingFace && !empty(Yii::$app->params['hf_api_key'])) {
                // HuggingFace puede procesar múltiples textos
                $modelo = Yii::$app->params['hf_embedding_model'] ?? 'sentence-transformers/paraphrase-multilingual-MiniLM-L12-v2';
                
                $client = new Client();
                $response = $client->createRequest()
                    ->setMethod('POST')
                    ->setUrl("https://api-inference.huggingface.co/pipeline/feature-extraction/{$modelo}")
                    ->addHeaders([
                        'Authorization' => 'Bearer ' . Yii::$app->params['hf_api_key'],
                        'Content-Type' => 'application/json'
                    ])
                    ->setContent(json_encode([
                        'inputs' => array_values($textosSinCache),
                        'options' => [
                            'wait_for_model' => false
                        ]
                    ]))
                    ->send();

                if ($response->isOk) {
                    $responseData = json_decode($response->content, true);
                    if (is_array($responseData)) {
                        $textosArray = array_values($textosSinCache);
                        foreach ($responseData as $index => $embedding) {
                            if (isset($textosArray[$index])) {
                                $texto = $textosArray[$index];
                                // Si es array anidado, tomar el primer elemento
                                if (isset($embedding[0]) && is_array($embedding[0])) {
                                    $embedding = $embedding[0];
                                }
                                $embeddings[$texto] = $embedding;
                                
                                // Guardar en cache
                                $cacheKey = 'embedding_' . md5($texto);
                                self::$cache[$cacheKey] = $embedding;
                                if ($yiiCache) {
                                    $yiiCache->set($cacheKey, $embedding, self::CACHE_TTL);
                                }
                            }
                        }
                    }
                }
            } else {
                // Fallback a OpenAI batch
            $client = new Client();
            $response = $client->createRequest()
                ->setMethod('POST')
                ->setUrl('https://api.openai.com/v1/embeddings')
                ->addHeaders([
                        'Authorization' => 'Bearer ' . Yii::$app->params['openai_api_key'],
                    'Content-Type' => 'application/json'
                ])
                ->setContent(json_encode([
                    'model' => 'text-embedding-3-small',
                        'input' => array_values($textosSinCache)
                ]))
                ->send();

            if ($response->isOk) {
                $responseData = json_decode($response->content, true);
                $data = $responseData['data'] ?? [];
                    $textosArray = array_values($textosSinCache);
                
                foreach ($data as $index => $item) {
                        if (isset($textosArray[$index])) {
                            $texto = $textosArray[$index];
                            $embedding = $item['embedding'] ?? null;
                            if ($embedding) {
                                $embeddings[$texto] = $embedding;
                                
                                // Guardar en cache
                                $cacheKey = 'embedding_' . md5($texto);
                                self::$cache[$cacheKey] = $embedding;
                                if ($yiiCache) {
                                    $yiiCache->set($cacheKey, $embedding, self::CACHE_TTL);
                                }
                            }
                        }
                    }
                }
                }
                
                \Yii::info("Embeddings generados en lote: " . count($embeddings) . " términos", 'embeddings');
            
        } catch (\Exception $e) {
            \Yii::error("Error en generación batch de embeddings: " . $e->getMessage(), 'embeddings');
        }
        
        return $embeddings;
    }
    
    /**
     * Obtener términos SNOMED para una categoría específica
     * @param string $categoria
     * @param int $limite
     * @return array
     */
    public static function obtenerTerminosSnomed($categoria, $limite = 100)
    {
        try {
            // Usar Snowstorm para obtener términos
            $snowstorm = new \frontend\components\Snowstorm();
            
            // Mapear categoría a método de Snowstorm
            $metodo = self::mapearCategoriaAMetodo($categoria);
            if (!$metodo) {
                return [];
            }
            
            $terminos = $snowstorm->$metodo('', $limite);
            
            // Extraer solo los textos de los términos
            $textos = [];
            foreach ($terminos as $termino) {
                if (isset($termino['text'])) {
                    $textos[] = $termino['text'];
                }
            }
            
            \Yii::info("Obtenidos {$categoria} términos SNOMED: " . count($textos), 'embeddings');
            return $textos;
            
        } catch (\Exception $e) {
            \Yii::error("Error obteniendo términos SNOMED: " . $e->getMessage(), 'embeddings');
            return [];
        }
    }
    
    /**
     * Mapear categoría a método de Snowstorm
     * @param string $categoria
     * @return string|null
     */
    private static function mapearCategoriaAMetodo($categoria)
    {
        $mapeo = [
            'diagnosticos' => 'getProblemas',
            'sintomas' => 'getHallazgos',
            'medicamentos' => 'getMedicamentosGenericos',
            'procedimientos' => 'getProcedimientos'
        ];
        
        return $mapeo[$categoria] ?? null;
    }
    
    /**
     * Limpiar cache de embeddings
     */
    public static function limpiarCache()
    {
        self::$cache = [];
        \Yii::info('Cache de embeddings limpiado', 'embeddings');
    }
    
    /**
     * Obtener estadísticas del cache
     * @return array
     */
    public static function getEstadisticasCache()
    {
        return [
            'total_embeddings' => count(self::$cache),
            'memoria_estimada' => memory_get_usage(true) - memory_get_usage(false)
        ];
    }
}
