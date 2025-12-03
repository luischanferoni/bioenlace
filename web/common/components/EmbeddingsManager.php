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
     * Generar embedding para un texto usando OpenAI
     * @param string $texto
     * @return array|null
     */
    public static function generarEmbedding($texto)
    {
        // Verificar cache primero
        $cacheKey = 'embedding_' . md5($texto);
        if (isset(self::$cache[$cacheKey])) {
            return self::$cache[$cacheKey];
        }
        
        try {
            $client = new Client();
            $response = $client->createRequest()
                ->setMethod('POST')
                ->setUrl('https://api.openai.com/v1/embeddings')
                ->addHeaders([
                    'Authorization' => 'Bearer ' . (Yii::$app->params['openai_api_key'] ?? ''),
                    'Content-Type' => 'application/json'
                ])
                ->setContent(json_encode([
                    'model' => 'text-embedding-3-small', // Modelo optimizado para embeddings
                    'input' => $texto
                ]))
                ->send();

            if ($response->isOk) {
                $responseData = json_decode($response->content, true);
                $embedding = $responseData['data'][0]['embedding'] ?? null;
                
                if ($embedding) {
                    // Guardar en cache
                    self::$cache[$cacheKey] = $embedding;
                    \Yii::info("Embedding generado para: " . substr($texto, 0, 50), 'embeddings');
                    return $embedding;
                }
            } else {
                \Yii::error('Error generando embedding: ' . $response->getStatusCode() . ' - ' . $response->getContent(), 'embeddings');
            }
            
        } catch (\Exception $e) {
            \Yii::error("Error en generación de embedding: " . $e->getMessage(), 'embeddings');
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
     * @return array
     */
    public static function generarEmbeddingsBatch($textos)
    {
        $embeddings = [];
        
        try {
            $client = new Client();
            $response = $client->createRequest()
                ->setMethod('POST')
                ->setUrl('https://api.openai.com/v1/embeddings')
                ->addHeaders([
                    'Authorization' => 'Bearer ' . (Yii::$app->params['openai_api_key'] ?? ''),
                    'Content-Type' => 'application/json'
                ])
                ->setContent(json_encode([
                    'model' => 'text-embedding-3-small',
                    'input' => $textos
                ]))
                ->send();

            if ($response->isOk) {
                $responseData = json_decode($response->content, true);
                $data = $responseData['data'] ?? [];
                
                foreach ($data as $index => $item) {
                    $embeddings[$textos[$index]] = $item['embedding'] ?? null;
                }
                
                \Yii::info("Embeddings generados en lote: " . count($embeddings) . " términos", 'embeddings');
            } else {
                \Yii::error('Error generando embeddings en lote: ' . $response->getStatusCode(), 'embeddings');
            }
            
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
