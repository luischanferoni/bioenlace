<?php

namespace common\components;

use Yii;

/**
 * Deduplicador de requests para evitar llamadas duplicadas
 */
class RequestDeduplicator
{
    private static $requestCache = [];
    private const CACHE_TTL = 300;
    private const SIMILITUD_MINIMA = 0.95;
    
    public static function buscarSimilar($prompt, $tipo = 'general')
    {
        $cacheKey = self::generarCacheKey($prompt, $tipo);
        
        if (isset(self::$requestCache[$cacheKey])) {
            $cached = self::$requestCache[$cacheKey];
            if (time() - $cached['timestamp'] < self::CACHE_TTL) {
                return $cached['response'];
            } else {
                unset(self::$requestCache[$cacheKey]);
            }
        }
        
        foreach (self::$requestCache as $key => $cached) {
            if ($cached['tipo'] !== $tipo) {
                continue;
            }
            
            if (time() - $cached['timestamp'] >= self::CACHE_TTL) {
                unset(self::$requestCache[$key]);
                continue;
            }
            
            $similitud = self::calcularSimilitud($prompt, $cached['prompt']);
            if ($similitud >= self::SIMILITUD_MINIMA) {
                return $cached['response'];
            }
        }
        
        return null;
    }
    
    public static function guardar($prompt, $response, $tipo = 'general')
    {
        $cacheKey = self::generarCacheKey($prompt, $tipo);
        
        self::$requestCache[$cacheKey] = [
            'prompt' => $prompt,
            'response' => $response,
            'tipo' => $tipo,
            'timestamp' => time()
        ];
        
        if (count(self::$requestCache) > 1000) {
            self::limpiarExpirados();
        }
    }
    
    private static function generarCacheKey($prompt, $tipo)
    {
        return $tipo . '_' . md5($prompt);
    }
    
    private static function calcularSimilitud($prompt1, $prompt2)
    {
        $p1 = strtolower(trim($prompt1));
        $p2 = strtolower(trim($prompt2));
        
        if ($p1 === $p2) {
            return 1.0;
        }
        
        $maxLen = max(strlen($p1), strlen($p2));
        if ($maxLen === 0) {
            return 1.0;
        }
        
        $distancia = levenshtein($p1, $p2);
        $similitudLevenshtein = 1 - ($distancia / $maxLen);
        
        $palabras1 = array_filter(explode(' ', $p1));
        $palabras2 = array_filter(explode(' ', $p2));
        
        $palabrasComunes = count(array_intersect($palabras1, $palabras2));
        $totalPalabras = count(array_unique(array_merge($palabras1, $palabras2)));
        
        $similitudPalabras = $totalPalabras > 0 ? ($palabrasComunes / $totalPalabras) : 0;
        
        return ($similitudLevenshtein * 0.3) + ($similitudPalabras * 0.7);
    }
    
    private static function limpiarExpirados()
    {
        $ahora = time();
        foreach (self::$requestCache as $key => $cached) {
            if ($ahora - $cached['timestamp'] >= self::CACHE_TTL) {
                unset(self::$requestCache[$key]);
            }
        }
    }
}
