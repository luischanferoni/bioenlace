<?php

namespace common\components\Ai\Providers\Google;

use Yii;
use common\components\Ai\Cost\AICostTracker;
use common\components\Assistant\EntryPoints\Chat\Preprocess\ChatPreprocessService;

/**
 * Simula cachedContents en local: parte estable + variable, registro en memoria y
 * estimación de cachedContentTokenCount cuando la API aún no devuelve hits.
 *
 * Prepara el payload Google (systemInstruction + user) para context caching implícito/explícito.
 */
final class VertexContextCacheSimulator
{
    /** @var array<string, array{stable: string, token_estimate: int, hits: int}> */
    private static $entries = [];

    public static function isEnabled(): bool
    {
        return (bool) (Yii::$app->params['vertex_context_cache_simulado'] ?? false);
    }

    /**
     * @param array<string, mixed> $proveedorIA
     */
    public static function assignPromptIfApplicable(array &$proveedorIA, string $contexto, string $fullPrompt): bool
    {
        if (($proveedorIA['tipo'] ?? '') !== 'google' || !self::isEnabled()) {
            return false;
        }

        $split = self::splitPrompt($contexto, $fullPrompt);
        if ($split === null) {
            return false;
        }

        $cacheKey = $split['cache_key'];
        self::registerEntry($cacheKey, $split['stable']);

        unset($proveedorIA['payload']['contents'], $proveedorIA['payload']['systemInstruction']);
        $proveedorIA['payload']['systemInstruction'] = [
            'parts' => [['text' => $split['stable']]],
        ];
        $proveedorIA['payload']['contents'] = [[
            'role' => 'user',
            'parts' => [['text' => $split['variable']]],
        ]];
        $proveedorIA['_vertex_cache_sim_key'] = $cacheKey;
        $proveedorIA['_vertex_cache_sim_tokens'] = self::$entries[$cacheKey]['token_estimate'];

        return true;
    }

    /**
     * Si la respuesta no trae cachedContentTokenCount, acumula la estimación local.
     */
    public static function complementarTracking(string $responseJson, ?string $contexto, ?string $cacheKey): void
    {
        if (!self::isEnabled() || $cacheKey === null || !isset(self::$entries[$cacheKey])) {
            return;
        }
        if (!class_exists(AICostTracker::class) || !AICostTracker::trackingHabilitado()) {
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

        $cachedReal = (int) ($meta['cachedContentTokenCount'] ?? 0);
        $prompt = (int) ($meta['promptTokenCount'] ?? 0);
        if ($cachedReal > 0 || $prompt <= 0) {
            return;
        }

        $estimado = min(self::$entries[$cacheKey]['token_estimate'], $prompt);
        if ($estimado <= 0) {
            return;
        }

        self::$entries[$cacheKey]['hits']++;
        AICostTracker::registrarCacheSimulada($estimado, $contexto);

        Yii::info(
            sprintf(
                'VertexContextCacheSimulator contexto=%s key=%s cached_sim=%d prompt=%d',
                (string) $contexto,
                $cacheKey,
                $estimado,
                $prompt
            ),
            'ia-cost'
        );
    }

    /**
     * @return array<string, mixed>
     */
    public static function getResumenSimulacion(): array
    {
        return [
            'habilitado' => self::isEnabled(),
            'entradas' => self::$entries,
        ];
    }

    public static function reset(): void
    {
        self::$entries = [];
    }

    /**
     * @return array{stable: string, variable: string, cache_key: string}|null
     */
    private static function splitPrompt(string $contexto, string $fullPrompt): ?array
    {
        switch ($contexto) {
            case 'asistente-preprocess':
                return [
                    'cache_key' => 'asistente-preprocess:v1',
                    'stable' => ChatPreprocessService::stablePromptPrefix(),
                    'variable' => ChatPreprocessService::userMessagePart(
                        self::extractUserFromPreprocessPrompt($fullPrompt)
                    ),
                ];

            case 'asistente-conversational':
                $marker = "Usuario:\n";
                $pos = strrpos($fullPrompt, $marker);
                if ($pos === false) {
                    return null;
                }
                return [
                    'cache_key' => 'asistente-conversational:v1',
                    'stable' => substr($fullPrompt, 0, $pos + strlen($marker)),
                    'variable' => substr($fullPrompt, $pos + strlen($marker)),
                ];

            default:
                return self::splitByLastMarker($contexto, $fullPrompt);
        }
    }

    /**
     * @return array{stable: string, variable: string, cache_key: string}|null
     */
    private static function splitByLastMarker(string $contexto, string $fullPrompt): ?array
    {
        $markers = [
            "Mensaje:\n",
            "Usuario:\n",
            "Texto de consulta:\n",
            "Transcripción:\n",
            "Entrada TOON (JSON compacto):\n",
        ];

        foreach ($markers as $marker) {
            $pos = strrpos($fullPrompt, $marker);
            if ($pos !== false && $pos > 20) {
                return [
                    'cache_key' => $contexto . ':split:v1',
                    'stable' => substr($fullPrompt, 0, $pos + strlen($marker)),
                    'variable' => substr($fullPrompt, $pos + strlen($marker)),
                ];
            }
        }

        return null;
    }

    private static function registerEntry(string $cacheKey, string $stable): void
    {
        if (!isset(self::$entries[$cacheKey])) {
            self::$entries[$cacheKey] = [
                'stable' => $stable,
                'token_estimate' => self::estimateTokens($stable),
                'hits' => 0,
            ];
            return;
        }

        self::$entries[$cacheKey]['stable'] = $stable;
        self::$entries[$cacheKey]['token_estimate'] = self::estimateTokens($stable);
    }

    private static function estimateTokens(string $text): int
    {
        $len = strlen($text);
        if ($len === 0) {
            return 0;
        }

        return max(1, (int) ceil($len / 4));
    }

    /**
     * Extrae el mensaje del usuario desde el prompt completo de preprocess (fallback).
     */
    public static function extractUserFromPreprocessPrompt(string $fullPrompt): string
    {
        $marker = "Mensaje:\n";
        $pos = strrpos($fullPrompt, $marker);
        if ($pos === false) {
            return $fullPrompt;
        }

        return substr($fullPrompt, $pos + strlen($marker));
    }
}
