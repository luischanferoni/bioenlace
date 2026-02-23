<?php

namespace common\components;

/**
 * Acumula métricas de uso de IA para pruebas de costos.
 * - Evitadas: cache, dedup, CPU, validación.
 * - Llamadas simuladas: cuando se ejecutan pruebas (runner o test unitario), no se hace HTTP.
 * Las pruebas nunca llaman a la IA real; solo el runner/test activan simulación.
 */
class AICostTracker
{
    private static $evitadaPorCache = 0;
    private static $evitadaPorDedup = 0;
    private static $evitadaPorCpu = 0;
    private static $evitadaPorValidacion = 0;
    private static $llamadaSimulada = 0;
    private static $llamadaReal = 0;

    /** @var bool Flag de ámbito de ejecución: true solo mientras corre el runner de pruebas o test unitario */
    private static $ejecucionPruebaActiva = false;

    /**
     * Iniciar una ejecución de prueba (siempre simula; no hay parámetro).
     * El runner de conversaciones o el test unitario lo llaman al inicio.
     */
    public static function iniciarEjecucionPrueba(): void
    {
        self::$ejecucionPruebaActiva = true;
    }

    /**
     * Finalizar la ejecución de prueba y limpiar el flag.
     */
    public static function finalizarEjecucionPrueba(): void
    {
        self::$ejecucionPruebaActiva = false;
    }

    /**
     * ¿Debe IAManager simular la llamada (no enviar HTTP)?
     * true solo cuando el runner de pruebas o el test unitario están activos.
     */
    public static function debeSimularIA(): bool
    {
        return self::$ejecucionPruebaActiva;
    }

    /**
     * Registrar que se evitó una llamada a IA.
     * @param string $motivo 'cache'|'dedup'|'cpu'|'validacion'
     * @param string|null $contexto
     */
    public static function registrarEvitada(string $motivo, $contexto = null): void
    {
        switch ($motivo) {
            case 'cache':
                self::$evitadaPorCache++;
                break;
            case 'dedup':
                self::$evitadaPorDedup++;
                break;
            case 'cpu':
                self::$evitadaPorCpu++;
                break;
            case 'validacion':
                self::$evitadaPorValidacion++;
                break;
            default:
                break;
        }
    }

    /**
     * Registrar que se habría llamado a la IA pero se simuló (no se envió request).
     */
    public static function registrarLlamadaSimulada($contexto = null, $tipoModelo = null): void
    {
        self::$llamadaSimulada++;
    }

    /**
     * Registrar que se realizó una llamada real a la IA (opcional; para métricas en producción).
     */
    public static function registrarLlamadaReal($contexto = null, $tipoModelo = null): void
    {
        self::$llamadaReal++;
    }

    /**
     * Resumen de contadores para el reporte.
     * @return array
     */
    public static function getResumen(): array
    {
        return [
            'evitada_por_cache' => self::$evitadaPorCache,
            'evitada_por_dedup' => self::$evitadaPorDedup,
            'evitada_por_cpu' => self::$evitadaPorCpu,
            'evitada_por_validacion' => self::$evitadaPorValidacion,
            'llamada_simulada' => self::$llamadaSimulada,
            'llamada_real' => self::$llamadaReal,
            'total_evitadas' => self::$evitadaPorCache + self::$evitadaPorDedup + self::$evitadaPorCpu + self::$evitadaPorValidacion,
        ];
    }

    /**
     * Limpiar contadores (entre ejecuciones de conversaciones o tests).
     */
    public static function reset(): void
    {
        self::$evitadaPorCache = 0;
        self::$evitadaPorDedup = 0;
        self::$evitadaPorCpu = 0;
        self::$evitadaPorValidacion = 0;
        self::$llamadaSimulada = 0;
        self::$llamadaReal = 0;
    }
}
