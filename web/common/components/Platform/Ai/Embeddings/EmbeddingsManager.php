<?php

namespace common\components\Platform\Ai\Embeddings;

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
     * Generar embedding para un texto usando HuggingFace
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
        if (class_exists('\common\components\Domain\Terminology\Application\Service\SnomedCommonTermsCacheService')) {
            $embeddingComun = \common\components\Domain\Terminology\Application\Service\SnomedCommonTermsCacheService::obtenerEmbedding($texto);
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
                if (class_exists('\common\components\Domain\Terminology\Application\Service\SnomedCommonTermsCacheService')) {
                    \common\components\Domain\Terminology\Application\Service\SnomedCommonTermsCacheService::guardarEmbedding($texto, $cached);
                }

                return $cached;
            }
        }

        try {
            $embedding = null;

            if ($useHuggingFace && !empty(Yii::$app->params['hf_api_key'])) {
                // Usar HuggingFace (más económico, especialmente para español)
                $embedding = self::generarEmbeddingHuggingFace($texto);
            } elseif ($useHuggingFace) {
                \Yii::warning('Embedding no generado: hf_api_key no configurada', 'embeddings');
            }

            if ($embedding) {
                // Guardar en cache de memoria
                self::$cache[$cacheKey] = $embedding;

                // Guardar en cache de Yii (persistente)
                if ($yiiCache) {
                    $yiiCache->set($cacheKey, $embedding, self::CACHE_TTL);
                }

                // Si es término SNOMED común, guardar en cache permanente
                if (class_exists('\common\components\Domain\Terminology\Application\Service\SnomedCommonTermsCacheService')) {
                    \common\components\Domain\Terminology\Application\Service\SnomedCommonTermsCacheService::guardarEmbedding($texto, $embedding);
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
            // Endpoint de HuggingFace Inference Providers (requiere /models/ en la ruta).
            // La cuota gratuita mensual es limitada: si se agota responde HTTP 402.
            $modelo = Yii::$app->params['hf_embedding_model'] ?? 'sentence-transformers/paraphrase-multilingual-MiniLM-L12-v2';

            $client = new Client();
            $response = $client->createRequest()
                ->setMethod('POST')
                ->setUrl("https://router.huggingface.co/hf-inference/models/{$modelo}")
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

            $status = $response->getStatusCode();
            $detalle = trim((string) $response->content);
            if (strlen($detalle) > 300) {
                $detalle = substr($detalle, 0, 300) . '...';
            }
            \Yii::warning("HuggingFace embedding falló: HTTP {$status} {$detalle}", 'embeddings');
        } catch (\Exception $e) {
            \Yii::warning("Error HuggingFace embedding: " . $e->getMessage(), 'embeddings');
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

