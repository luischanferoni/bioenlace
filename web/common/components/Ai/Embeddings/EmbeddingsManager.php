<?php

namespace common\components\Ai\Embeddings;

use Yii;
use yii\httpclient\Client;

/**
 * Componente para manejo de embeddings semánticos
 * Genera vectores numéricos y calcula similitudes para búsqueda semántica real
 */
class EmbeddingsManager
{
    private static $cache = [];
    // TTL extendido a 30 días para reducir costos (optimización agresiva)
    private const CACHE_TTL = 2592000; // 30 días
    private const SIMILITUD_MINIMA = 0.7; // Umbral mínimo de similitud

    /**
     * Generar embedding para un texto usando HuggingFace o OpenAI
     * @param string $texto
     * @param bool $useHuggingFace Si true, usa modelos de HuggingFace (más económico)
     * @return array|null
     */
    public static function generarEmbedding($texto, $useHuggingFace = true)
    {
        // Validación previa: texto vacío
        $texto = trim($texto);
        if (empty($texto) || strlen($texto) < 2) {
            \Yii::warning("Texto vacío o muy corto para generar embedding", 'embeddings');
            return null;
        }

        // OPTIMIZACIÓN: Verificar cache de términos SNOMED comunes primero (más rápido)
        if (class_exists('\common\components\Terminology\Snomed\SnomedCommonTermsCache')) {
            $embeddingComun = \common\components\Terminology\Snomed\SnomedCommonTermsCache::obtenerEmbedding($texto);
            if ($embeddingComun !== null) {
                return $embeddingComun;
            }
        }

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

                // Si es término común, guardar en cache permanente también
                if (class_exists('\common\components\Terminology\Snomed\SnomedCommonTermsCache')) {
                    \common\components\Terminology\Snomed\SnomedCommonTermsCache::guardarEmbedding($texto, $cached);
                }

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
            // Solo intentar si la API key está configurada y parece válida (no vacía y tiene formato correcto)
            if (
                !$embedding && !empty(Yii::$app->params['openai_api_key']) &&
                strlen(trim(Yii::$app->params['openai_api_key'])) > 10 &&
                strpos(Yii::$app->params['openai_api_key'], 'sk-') === 0
            ) {
                $embedding = self::generarEmbeddingOpenAI($texto);
            }

            if ($embedding) {
                // Guardar en cache de memoria
                self::$cache[$cacheKey] = $embedding;

                // Guardar en cache de Yii (persistente)
                if ($yiiCache) {
                    $yiiCache->set($cacheKey, $embedding, self::CACHE_TTL);
                }

                // Si es término SNOMED común, guardar en cache permanente
                if (class_exists('\common\components\Terminology\Snomed\SnomedCommonTermsCache')) {
                    \common\components\Terminology\Snomed\SnomedCommonTermsCache::guardarEmbedding($texto, $embedding);
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
                ->setUrl("https://router.huggingface.co/hf-inference/{$modelo}")
                ->addHeaders([
                    'Authorization' => 'Bearer ' . Yii::$app->params['hf_api_key'],
                    'Content-Type' => 'application/json'
                ])
                ->setContent(json_encode([
                    'inputs' => $texto,
                    'options' => [
                        'wait_for_model' => false
                    ]
                ]))
                ->send();

            if ($response->isOk) {
                $data = json_decode($response->content, true);

                // HuggingFace devuelve array de floats directamente
                if (is_array($data) && !empty($data) && is_numeric($data[0] ?? null)) {
                    return $data;
                }

                // A veces devuelve [ [ ... ] ]
                if (is_array($data) && isset($data[0]) && is_array($data[0]) && is_numeric($data[0][0] ?? null)) {
                    return $data[0];
                }
            }

            \Yii::warning("HuggingFace embedding falló: " . $response->getStatusCode(), 'embeddings');
        } catch (\Exception $e) {
            \Yii::warning("Error HuggingFace embedding: " . $e->getMessage(), 'embeddings');
        }

        return null;
    }

    /**
     * Generar embedding usando OpenAI (más caro, pero más preciso)
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
                    'input' => $texto,
                    'model' => Yii::$app->params['openai_embedding_model'] ?? 'text-embedding-3-small'
                ]))
                ->send();

            if ($response->isOk) {
                $data = json_decode($response->content, true);
                if (isset($data['data'][0]['embedding'])) {
                    return $data['data'][0]['embedding'];
                }
            }

            \Yii::warning("OpenAI embedding falló: " . $response->getStatusCode(), 'embeddings');
        } catch (\Exception $e) {
            \Yii::warning("Error OpenAI embedding: " . $e->getMessage(), 'embeddings');
        }

        return null;
    }

    /**
     * Generar embeddings en batch para múltiples textos
     * @param array $textos
     * @param bool $useHuggingFace
     * @return array Array asociativo texto => embedding
     */
    public static function generarEmbeddingsBatch($textos, $useHuggingFace = true)
    {
        $resultados = [];

        if (empty($textos) || !is_array($textos)) {
            return $resultados;
        }

        foreach ($textos as $texto) {
            $embedding = self::generarEmbedding($texto, $useHuggingFace);
            if ($embedding) {
                $resultados[$texto] = $embedding;
            }
        }

        return $resultados;
    }

    /**
     * Calcular similitud coseno entre dos embeddings
     * @param array $vec1
     * @param array $vec2
     * @return float
     */
    public static function calcularSimilitudCoseno($vec1, $vec2)
    {
        if (empty($vec1) || empty($vec2) || count($vec1) !== count($vec2)) {
            return 0;
        }

        $dot = 0;
        $norm1 = 0;
        $norm2 = 0;

        $n = count($vec1);
        for ($i = 0; $i < $n; $i++) {
            $a = (float)$vec1[$i];
            $b = (float)$vec2[$i];
            $dot += $a * $b;
            $norm1 += $a * $a;
            $norm2 += $b * $b;
        }

        if ($norm1 == 0 || $norm2 == 0) {
            return 0;
        }

        return $dot / (sqrt($norm1) * sqrt($norm2));
    }
}

