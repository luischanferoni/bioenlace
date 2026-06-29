<?php

namespace common\components\Platform\Ai\Cost;

/**
 * Estima costo USD a partir del resumen de {@see AICostTracker}.
 */
final class AICostEstimationService
{
    /**
     * @param array<string, mixed> $resumen
     * @return array<string, mixed>
     */
    public static function estimarDesdeResumen(array $resumen): array
    {
        $tokens = is_array($resumen['tokens'] ?? null) ? $resumen['tokens'] : [];
        $prompt = (int) ($tokens['prompt_token_count'] ?? 0);
        $cached = (int) ($tokens['cached_content_token_count'] ?? 0);
        $candidates = (int) ($tokens['candidates_token_count'] ?? 0);

        $fuente = 'medido';
        if ($prompt <= 0 && $candidates <= 0) {
            [$prompt, $cached, $candidates] = self::proyectarDesdeLlamadas($resumen);
            $fuente = 'referencia';
        }

        $tarifas = AICostReferenceMetadata::tarifasUsdPorMillon();
        $billableInput = max(0, $prompt - $cached);

        $costoInput = $billableInput * $tarifas['input'] / 1_000_000;
        $costoCache = $cached * $tarifas['input_cacheado'] / 1_000_000;
        $costoOutput = $candidates * $tarifas['output'] / 1_000_000;
        $total = $costoInput + $costoCache + $costoOutput;

        return [
            'modelo' => AICostReferenceMetadata::modeloReferencia(),
            'fuente_tokens' => $fuente,
            'prompt_tokens' => $prompt,
            'cached_tokens' => $cached,
            'billable_input_tokens' => $billableInput,
            'candidates_tokens' => $candidates,
            'usd' => [
                'input' => round($costoInput, 8),
                'input_cacheado' => round($costoCache, 8),
                'output' => round($costoOutput, 8),
                'total' => round($total, 8),
            ],
            'tarifas_usd_por_millon' => $tarifas,
        ];
    }

    /**
     * @param array<string, mixed> $resumen
     * @return array{0:int,1:int,2:int}
     */
    private static function proyectarDesdeLlamadas(array $resumen): array
    {
        $prompt = 0;
        $cached = 0;
        $candidates = 0;

        $porContexto = is_array($resumen['por_contexto'] ?? null) ? $resumen['por_contexto'] : [];
        foreach ($porContexto as $ctx => $stats) {
            if (!is_array($stats)) {
                continue;
            }
            $llamadas = max(0, (int) ($stats['llamadas'] ?? 0));
            if ($llamadas <= 0) {
                continue;
            }
            $ref = AICostReferenceMetadata::tokensParaContexto((string) $ctx);
            $ctxPrompt = $ref['prompt_tokens'] * $llamadas;
            $ctxCached = (int) round($ctxPrompt * $ref['cached_ratio']);
            $prompt += $ctxPrompt;
            $cached += $ctxCached;
            $candidates += $ref['candidates_tokens'] * $llamadas;
        }

        $simuladas = max(0, (int) ($resumen['llamada_simulada'] ?? 0));
        $reales = max(0, (int) ($resumen['llamada_real'] ?? 0));
        $contadas = 0;
        foreach ($porContexto as $stats) {
            if (is_array($stats)) {
                $contadas += max(0, (int) ($stats['llamadas'] ?? 0));
            }
        }
        $sinContexto = max(0, ($simuladas + $reales) - $contadas);
        if ($sinContexto > 0) {
            $ref = AICostReferenceMetadata::tokensParaContexto(null);
            $extraPrompt = $ref['prompt_tokens'] * $sinContexto;
            $prompt += $extraPrompt;
            $cached += (int) round($extraPrompt * $ref['cached_ratio']);
            $candidates += $ref['candidates_tokens'] * $sinContexto;
        }

        return [$prompt, $cached, $candidates];
    }
}
