<?php

namespace common\components;

use Yii;

/**
 * Cache de términos SNOMED más comunes para evitar generar embeddings repetidamente
 * Pre-genera embeddings de los términos más usados y los cachea permanentemente
 */
class SnomedCommonTermsCache
{
    private const CACHE_KEY_PREFIX = 'snomed_common_';
    private const CACHE_TTL_PERMANENT = 0; // Sin expiración (permanente hasta limpieza manual)
    
    /**
     * Términos SNOMED más comunes pre-definidos
     * Estos términos se usan frecuentemente y sus embeddings se cachean permanentemente
     */
    private static $terminosComunes = [
        // Síntomas comunes
        'dolor', 'fiebre', 'tos', 'malestar', 'nausea', 'vomito', 'diarrea',
        'dolor de cabeza', 'cefalea', 'mareo', 'cansancio', 'fatiga',
        
        // Diagnósticos comunes
        'gripe', 'resfriado', 'hipertensión', 'diabetes', 'asma', 'bronquitis',
        'gastritis', 'ulcera', 'artritis', 'osteoporosis',
        
        // Medicamentos comunes
        'paracetamol', 'ibuprofeno', 'aspirina', 'amoxicilina', 'omeprazol',
        'metformina', 'losartan', 'atenolol', 'amlodipino',
        
        // Procedimientos comunes
        'radiografia', 'ecografia', 'analisis de sangre', 'electrocardiograma',
        'tomografia', 'resonancia magnetica',
        
        // Partes del cuerpo
        'cabeza', 'cuello', 'pecho', 'abdomen', 'extremidades', 'brazo', 'pierna',
        'ojo', 'oído', 'nariz', 'boca', 'garganta',
    ];
    
    /**
     * Obtener embedding de un término común (desde cache permanente)
     * @param string $termino
     * @return array|null
     */
    public static function obtenerEmbedding($termino)
    {
        $terminoLower = mb_strtolower(trim($termino), 'UTF-8');
        
        // Verificar si es un término común
        if (!in_array($terminoLower, self::$terminosComunes)) {
            return null; // No es término común, no usar este cache
        }
        
        $cacheKey = self::CACHE_KEY_PREFIX . md5($terminoLower);
        $yiiCache = Yii::$app->cache;
        
        if ($yiiCache) {
            $cached = $yiiCache->get($cacheKey);
            if ($cached !== false) {
                \Yii::info("Embedding SNOMED común obtenido desde cache permanente: {$terminoLower}", 'snomed-cache');
                return $cached;
            }
        }
        
        return null;
    }
    
    /**
     * Guardar embedding de término común (cache permanente)
     * @param string $termino
     * @param array $embedding
     */
    public static function guardarEmbedding($termino, $embedding)
    {
        $terminoLower = mb_strtolower(trim($termino), 'UTF-8');
        
        // Solo guardar si es término común
        if (!in_array($terminoLower, self::$terminosComunes)) {
            return;
        }
        
        $cacheKey = self::CACHE_KEY_PREFIX . md5($terminoLower);
        $yiiCache = Yii::$app->cache;
        
        if ($yiiCache && $embedding) {
            // Cache permanente (TTL = 0 significa sin expiración en algunos sistemas)
            // Usar TTL muy largo como alternativa (10 años)
            $yiiCache->set($cacheKey, $embedding, 315360000); // 10 años
            \Yii::info("Embedding SNOMED común guardado en cache permanente: {$terminoLower}", 'snomed-cache');
        }
    }
    
    /**
     * Pre-generar embeddings de todos los términos comunes
     * Se puede ejecutar una vez al inicio o periódicamente
     * @return int Número de embeddings generados
     */
    public static function preGenerarEmbeddings()
    {
        $generados = 0;
        
        foreach (self::$terminosComunes as $termino) {
            // Verificar si ya existe en cache
            if (self::obtenerEmbedding($termino) !== null) {
                continue; // Ya está cacheado
            }
            
            // Generar embedding
            $embedding = EmbeddingsManager::generarEmbedding($termino, true);
            if ($embedding) {
                self::guardarEmbedding($termino, $embedding);
                $generados++;
            }
        }
        
        \Yii::info("Pre-generados {$generados} embeddings de términos SNOMED comunes", 'snomed-cache');
        return $generados;
    }
    
    /**
     * Verificar si un término es común (para optimización)
     * @param string $termino
     * @return bool
     */
    public static function esTerminoComun($termino)
    {
        $terminoLower = mb_strtolower(trim($termino), 'UTF-8');
        return in_array($terminoLower, self::$terminosComunes);
    }
    
    /**
     * Agregar término a la lista de comunes (dinámico)
     * @param string $termino
     */
    public static function agregarTerminoComun($termino)
    {
        $terminoLower = mb_strtolower(trim($termino), 'UTF-8');
        if (!in_array($terminoLower, self::$terminosComunes)) {
            self::$terminosComunes[] = $terminoLower;
            \Yii::info("Término agregado a lista de comunes: {$terminoLower}", 'snomed-cache');
        }
    }
}

