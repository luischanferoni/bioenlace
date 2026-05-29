<?php

namespace common\components\Ai\Cost;

use Yii;

/**
 * Acumula métricas de uso de IA para pruebas de costos y, opcionalmente, producción.
 *
 * - Evitadas: cache de aplicación, dedup, CPU, validación.
 * - Llamadas simuladas: runner de pruebas / tests (nunca HTTP).
 * - Uso Gemini (si ia_usage_tracking_habilitado): tokens y cachedContentTokenCount por contexto.
 *
 * @see web/docs/costos/pruebas-costos-ia.md
 * @see web/docs/costos/estrategias-reduccion/monitoreo.md
 */
class AICostTracker
{
    private static $evitadaPorCache = 0;
    private static $evitadaPorDedup = 0;
    private static $evitadaPorCpu = 0;
    private static $evitadaPorValidacion = 0;
    private static $llamadaSimulada = 0;
    private static $llamadaReal = 0;

    /** @var int */
    private static $promptTokens = 0;
    /** @var int Tokens facturados a tarifa cacheada (usageMetadata.cachedContentTokenCount) */
    private static $cachedContentTokens = 0;
    /** @var int */
    private static $candidatesTokens = 0;
    /** @var int */
    private static $thoughtsTokens = 0;

    /** @var array<string, array{llamadas:int,prompt_tokens:int,cached_tokens:int,candidates_tokens:int}> */
    private static $porContexto = [];

    /** @var bool Flag de ámbito de ejecución: true solo mientras corre el runner de pruebas o test unitario */
    private static $ejecucionPruebaActiva = false;

    public static function iniciarEjecucionPrueba(): void
    {
        self::$ejecucionPruebaActiva = true;
    }

    public static function finalizarEjecucionPrueba(): void
    {
        self::$ejecucionPruebaActiva = false;
    }

    public static function debeSimularIA(): bool
    {
        return self::$ejecucionPruebaActiva;
    }

    /**
     * Registro de tokens Gemini en producción o staging (params ia_usage_tracking_habilitado).
     */
    public static function trackingHabilitado(): bool
    {
        if (self::$ejecucionPruebaActiva) {
            return true;
        }

        return (bool) (Yii::$app->params['ia_usage_tracking_habilitado'] ?? false);
    }

    /**
     * @param string $motivo 'cache'|'dedup'|'cpu'|'validacion'
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

    public static function registrarLlamadaSimulada($contexto = null, $tipoModelo = null): void
    {
        self::$llamadaSimulada++;
    }

    public static function registrarLlamadaReal($contexto = null, $tipoModelo = null): void
    {
        if (!self::trackingHabilitado()) {
            return;
        }
        self::$llamadaReal++;
        self::touchContexto((string) ($contexto ?? 'desconocido'));
    }

    /**
     * Lee usageMetadata de una respuesta JSON de Gemini / Vertex (camelCase en REST).
     *
     * @see https://ai.google.dev/gemini-api/docs/tokens
     */
    public static function registrarUsoDesdeRespuestaGemini(string $responseJson, ?string $contexto = null): void
    {
        if (!self::trackingHabilitado() || $responseJson === '') {
            return;
        }

        $data = json_decode($responseJson, true);
        if (!is_array($data)) {
            return;
        }

        $meta = $data['usageMetadata'] ?? null;
        if (!is_array($meta)) {
            return;
        }

        $prompt = (int) ($meta['promptTokenCount'] ?? 0);
        $cached = (int) ($meta['cachedContentTokenCount'] ?? 0);
        $candidates = (int) ($meta['candidatesTokenCount'] ?? 0);
        $thoughts = (int) ($meta['thoughtsTokenCount'] ?? 0);

        self::$promptTokens += $prompt;
        self::$cachedContentTokens += $cached;
        self::$candidatesTokens += $candidates;
        self::$thoughtsTokens += $thoughts;

        $ctx = (string) ($contexto ?? 'desconocido');
        self::touchContexto($ctx);
        self::$porContexto[$ctx]['llamadas']++;
        self::$porContexto[$ctx]['prompt_tokens'] += $prompt;
        self::$porContexto[$ctx]['cached_tokens'] += $cached;
        self::$porContexto[$ctx]['candidates_tokens'] += $candidates;

        Yii::info(
            sprintf(
                'AICostTracker contexto=%s prompt=%d cached=%d candidates=%d',
                $ctx,
                $prompt,
                $cached,
                $candidates
            ),
            'ia-cost'
        );
    }

    /**
     * @return array<string, mixed>
     */
    public static function getResumen(): array
    {
        $billableInput = max(0, self::$promptTokens - self::$cachedContentTokens);
        $ratioCached = self::$promptTokens > 0
            ? round(self::$cachedContentTokens / self::$promptTokens, 4)
            : 0.0;

        return [
            'evitada_por_cache' => self::$evitadaPorCache,
            'evitada_por_dedup' => self::$evitadaPorDedup,
            'evitada_por_cpu' => self::$evitadaPorCpu,
            'evitada_por_validacion' => self::$evitadaPorValidacion,
            'llamada_simulada' => self::$llamadaSimulada,
            'llamada_real' => self::$llamadaReal,
            'total_evitadas' => self::$evitadaPorCache + self::$evitadaPorDedup + self::$evitadaPorCpu + self::$evitadaPorValidacion,
            'tokens' => [
                'prompt_token_count' => self::$promptTokens,
                'cached_content_token_count' => self::$cachedContentTokens,
                'billable_input_token_count' => $billableInput,
                'candidates_token_count' => self::$candidatesTokens,
                'thoughts_token_count' => self::$thoughtsTokens,
                'ratio_input_en_cache' => $ratioCached,
            ],
            'por_contexto' => self::$porContexto,
            'tracking_habilitado' => self::trackingHabilitado(),
        ];
    }

    public static function reset(): void
    {
        self::$evitadaPorCache = 0;
        self::$evitadaPorDedup = 0;
        self::$evitadaPorCpu = 0;
        self::$evitadaPorValidacion = 0;
        self::$llamadaSimulada = 0;
        self::$llamadaReal = 0;
        self::$promptTokens = 0;
        self::$cachedContentTokens = 0;
        self::$candidatesTokens = 0;
        self::$thoughtsTokens = 0;
        self::$porContexto = [];
    }

    private static function touchContexto(string $ctx): void
    {
        if (!isset(self::$porContexto[$ctx])) {
            self::$porContexto[$ctx] = [
                'llamadas' => 0,
                'prompt_tokens' => 0,
                'cached_tokens' => 0,
                'candidates_tokens' => 0,
            ];
        }
    }
}
