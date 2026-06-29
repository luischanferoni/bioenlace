<?php

namespace common\components\Platform\Ai\Cost;

use common\components\Platform\Core\Product\ProductMetadataPaths;
use Symfony\Component\Yaml\Yaml;
use Yii;

/**
 * Tarifas y tokens de referencia para estimación de costos IA.
 *
 * @see web/common/metadata/bioenlace/ai/ai-cost-reference.yaml
 * @see web/docs/costos/costos-api.md
 */
final class AICostReferenceMetadata
{
    /** @var array<string, mixed>|null */
    private static ?array $config = null;

    public static function resetCacheForTests(): void
    {
        self::$config = null;
    }

    /**
     * @return array<string, mixed>
     */
    public static function load(): array
    {
        if (self::$config !== null) {
            return self::$config;
        }

        self::$config = [];
        $path = ProductMetadataPaths::aiCostReferenceFile();
        if (!is_file($path)) {
            return self::$config;
        }

        try {
            $data = Yaml::parseFile($path);
        } catch (\Throwable $e) {
            Yii::warning('AICostReferenceMetadata: YAML inválido: ' . $e->getMessage(), __METHOD__);

            return self::$config;
        }

        self::$config = is_array($data) ? $data : [];

        return self::$config;
    }

    /**
     * @return array{prompt_tokens:int,candidates_tokens:int,cached_ratio:float}
     */
    public static function tokensParaContexto(?string $contexto): array
    {
        $config = self::load();
        $defecto = $config['llamada_referencia'] ?? [];
        if (!is_array($defecto)) {
            $defecto = [];
        }

        $ctx = trim((string) $contexto);
        $contextos = $config['contextos'] ?? [];
        $override = ($ctx !== '' && is_array($contextos) && isset($contextos[$ctx]) && is_array($contextos[$ctx]))
            ? $contextos[$ctx]
            : [];

        $merged = array_merge($defecto, $override);

        return [
            'prompt_tokens' => max(0, (int) ($merged['prompt_tokens'] ?? 1000)),
            'candidates_tokens' => max(0, (int) ($merged['candidates_tokens'] ?? 500)),
            'cached_ratio' => self::clampRatio($merged['cached_ratio'] ?? 0.25),
        ];
    }

    /**
     * @return array{input:float,input_cacheado:float,output:float}
     */
    public static function tarifasUsdPorMillon(): array
    {
        $config = self::load();
        $tarifas = $config['tarifas_usd_por_millon'] ?? [];
        if (!is_array($tarifas)) {
            $tarifas = [];
        }

        return [
            'input' => (float) ($tarifas['input'] ?? 0.10),
            'input_cacheado' => (float) ($tarifas['input_cacheado'] ?? 0.01),
            'output' => (float) ($tarifas['output'] ?? 0.40),
        ];
    }

    public static function modeloReferencia(): string
    {
        $config = self::load();

        return trim((string) ($config['modelo'] ?? 'gemini-2.5-flash-lite'));
    }

    private static function clampRatio($value): float
    {
        $ratio = (float) $value;
        if ($ratio < 0.0) {
            return 0.0;
        }
        if ($ratio > 1.0) {
            return 1.0;
        }

        return $ratio;
    }
}
