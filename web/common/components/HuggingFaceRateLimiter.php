<?php

namespace common\components;

use Yii;

/**
 * Rate Limiter inteligente para HuggingFace API
 */
class HuggingFaceRateLimiter
{
    private static $lastRequestTime = [];
    private static $circuitBreaker = [];
    
    private const MIN_INTERVAL_MS = 100;
    private const CIRCUIT_BREAKER_THRESHOLD = 5;
    
    public static function puedeHacerRequest($endpoint, $critico = false)
    {
        $now = microtime(true) * 1000;
        
        if (self::circuitBreakerAbierto($endpoint)) {
            return false;
        }
        
        if (isset(self::$lastRequestTime[$endpoint])) {
            $tiempoDesdeUltimo = $now - self::$lastRequestTime[$endpoint];
            if ($tiempoDesdeUltimo < self::MIN_INTERVAL_MS && !$critico) {
                return false;
            }
        }
        
        self::$lastRequestTime[$endpoint] = $now;
        return true;
    }
    
    public static function registrarExito($endpoint)
    {
        if (isset(self::$circuitBreaker[$endpoint])) {
            self::$circuitBreaker[$endpoint]['errores_consecutivos'] = 0;
            if (self::$circuitBreaker[$endpoint]['abierto']) {
                self::$circuitBreaker[$endpoint]['abierto'] = false;
            }
        }
    }
    
    public static function registrarError($endpoint, $statusCode = 0)
    {
        if (!isset(self::$circuitBreaker[$endpoint])) {
            self::$circuitBreaker[$endpoint] = [
                'errores_consecutivos' => 0,
                'abierto' => false,
                'ultimo_error' => time()
            ];
        }
        
        self::$circuitBreaker[$endpoint]['errores_consecutivos']++;
        self::$circuitBreaker[$endpoint]['ultimo_error'] = time();
        
        if (self::$circuitBreaker[$endpoint]['errores_consecutivos'] >= self::CIRCUIT_BREAKER_THRESHOLD) {
            self::$circuitBreaker[$endpoint]['abierto'] = true;
        }
        
        if ($statusCode === 429) {
            $waitTime = min(pow(2, self::$circuitBreaker[$endpoint]['errores_consecutivos']) * 1000, 30000);
            usleep($waitTime * 1000);
        }
    }
    
    private static function circuitBreakerAbierto($endpoint)
    {
        if (!isset(self::$circuitBreaker[$endpoint])) {
            return false;
        }
        
        if (self::$circuitBreaker[$endpoint]['abierto']) {
            $tiempoDesdeError = time() - self::$circuitBreaker[$endpoint]['ultimo_error'];
            if ($tiempoDesdeError > 60) {
                self::$circuitBreaker[$endpoint]['abierto'] = false;
                self::$circuitBreaker[$endpoint]['errores_consecutivos'] = 0;
                return false;
            }
            return true;
        }
        
        return false;
    }
}
