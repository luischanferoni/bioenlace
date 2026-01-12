<?php

namespace common\queries;

use Yii;

/**
 * Registro centralizado de business queries
 * Carga queries desde JSON y permite buscarlas y ejecutarlas
 */
class BusinessQueryRegistry
{
    private static $queries = null;
    const METADATA_FILE = '@common/queries/metadata/business_queries.json';
    
    /**
     * Cargar queries desde JSON
     * @param bool $useCache
     * @return array
     */
    public static function loadQueries($useCache = true)
    {
        if (self::$queries !== null && $useCache) {
            return self::$queries;
        }
        
        $cache = Yii::$app->cache;
        $cacheKey = 'business_queries_registry';
        
        if ($useCache && $cache) {
            $cached = $cache->get($cacheKey);
            if ($cached !== false) {
                self::$queries = $cached;
                return $cached;
            }
        }
        
        $jsonPath = Yii::getAlias(self::METADATA_FILE);
        
        if (!file_exists($jsonPath)) {
            Yii::warning("Archivo de metadatos de business queries no encontrado: {$jsonPath}", 'business-query-registry');
            self::$queries = [];
            return [];
        }
        
        $content = file_get_contents($jsonPath);
        $data = json_decode($content, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            Yii::error("Error parseando JSON de business queries: " . json_last_error_msg(), 'business-query-registry');
            self::$queries = [];
            return [];
        }
        
        self::$queries = $data['queries'] ?? [];
        
        // Guardar en cache
        if ($cache) {
            $cache->set($cacheKey, self::$queries, 3600); // 1 hora
        }
        
        return self::$queries;
    }
    
    /**
     * Buscar query que coincida con criterios
     * @param array $criteria Criterios de búsqueda (search_keywords, entity_type, query_type)
     * @param string $userQuery Consulta original del usuario
     * @return array|null Query encontrada o null
     */
    public static function findMatchingQuery($criteria, $userQuery)
    {
        $queries = self::loadQueries();
        
        if (empty($queries)) {
            return null;
        }
        
        $queryLower = strtolower($userQuery);
        $keywords = array_map('strtolower', $criteria['search_keywords'] ?? []);
        
        $bestMatch = null;
        $bestScore = 0;
        
        foreach ($queries as $query) {
            // Saltar queries inactivas
            if (isset($query['active']) && !$query['active']) {
                continue;
            }
            
            $score = 0;
            
            // Score por keywords
            $queryKeywords = $query['keywords'] ?? [];
            foreach ($queryKeywords as $keyword) {
                $keywordLower = strtolower($keyword);
                
                // Coincidencia exacta en keywords extraídos
                if (in_array($keywordLower, $keywords)) {
                    $score += 10;
                }
                
                // Coincidencia parcial en query original
                if (stripos($queryLower, $keywordLower) !== false) {
                    $score += 5;
                }
            }
            
            // Score por entity_type
            if (!empty($criteria['entity_type']) && 
                !empty($query['entity_type']) &&
                strtolower($query['entity_type']) === strtolower($criteria['entity_type'])) {
                $score += 15;
            }
            
            // Score por query_type (ranking, metric, etc.)
            if (!empty($criteria['query_type']) && 
                !empty($query['query_type']) &&
                strtolower($query['query_type']) === strtolower($criteria['query_type'])) {
                $score += 10;
            }
            
            // Bonus si todos los keywords principales coinciden
            if (count($queryKeywords) > 0) {
                $matchedKeywords = 0;
                foreach ($queryKeywords as $keyword) {
                    if (stripos($queryLower, strtolower($keyword)) !== false) {
                        $matchedKeywords++;
                    }
                }
                if ($matchedKeywords === count($queryKeywords)) {
                    $score += 20; // Bonus alto por coincidencia completa
                }
            }
            
            if ($score > $bestScore) {
                $bestScore = $score;
                $bestMatch = $query;
            }
        }
        
        // Solo retornar si el score es suficientemente alto
        return $bestScore >= 10 ? $bestMatch : null;
    }
    
    /**
     * Ejecutar query
     * @param array $query Query metadata
     * @param array $params Parámetros para el método
     * @return mixed Resultado de la ejecución
     * @throws \Exception
     */
    public static function executeQuery($query, $params = [])
    {
        $className = $query['class'];
        $methodName = $query['method'];
        
        if (!class_exists($className)) {
            throw new \Exception("Clase no encontrada: {$className}");
        }
        
        if (!method_exists($className, $methodName)) {
            throw new \Exception("Método no encontrado: {$className}::{$methodName}");
        }
        
        // Validar parámetros requeridos
        $requiredParams = [];
        foreach ($query['parameters'] ?? [] as $param) {
            if (!empty($param['required']) && !isset($params[$param['name']])) {
                $requiredParams[] = $param['name'];
            }
        }
        
        if (!empty($requiredParams)) {
            throw new \Exception("Parámetros requeridos faltantes: " . implode(', ', $requiredParams));
        }
        
        // Ejecutar método estático
        return call_user_func_array([$className, $methodName], array_values($params));
    }
    
    /**
     * Obtener todas las queries disponibles
     * @return array
     */
    public static function getAllQueries()
    {
        return self::loadQueries();
    }
    
    /**
     * Obtener query por ID
     * @param string $id
     * @return array|null
     */
    public static function getQueryById($id)
    {
        $queries = self::loadQueries();
        
        foreach ($queries as $query) {
            if (($query['id'] ?? null) === $id) {
                return $query;
            }
        }
        
        return null;
    }
}
