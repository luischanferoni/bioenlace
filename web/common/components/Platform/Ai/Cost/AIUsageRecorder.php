<?php

namespace common\components\Platform\Ai\Cost;

use Yii;

/**
 * Persiste una línea JSONL por llamada real a IA.
 *
 * El detalle por llamada no va a la base: a 38 M llamadas/mes no escala. Va a
 * un archivo compartido que un job nocturno agrega en `ai_usage_daily`.
 *
 * No guarda prompt ni respuesta: solo contadores de tokens, costo, latencia,
 * contexto, efector y un hash del prompt para deduplicación/análisis.
 *
 * @see AIUsageAggregator
 * @see docs/negocio/costos/estrategias-reduccion/monitoreo.md
 */
final class AIUsageRecorder
{
    /** Directorio compartido por web y consola (Yii alias). */
    private const DEFAULT_DIR_ALIAS = '@common/runtime/ai-usage';

    public static function enabled(): bool
    {
        return (bool) (Yii::$app->params['ai_usage_persist_habilitado'] ?? false);
    }

    public static function logDir(): string
    {
        $configured = (string) (Yii::$app->params['ai_usage_log_dir'] ?? self::DEFAULT_DIR_ALIAS);
        $dir = str_starts_with($configured, '@') ? Yii::getAlias($configured) : $configured;
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        return rtrim($dir, '/\\');
    }

    public static function logPath(?string $date = null): string
    {
        return self::logDir() . '/ai-usage-' . ($date ?? date('Y-m-d')) . '.jsonl';
    }

    /**
     * @return list<string>
     */
    public static function availableDates(): array
    {
        $dates = [];
        foreach (glob(self::logDir() . '/ai-usage-*.jsonl') ?: [] as $path) {
            if (preg_match('/ai-usage-(\d{4}-\d{2}-\d{2})\.jsonl$/', $path, $m) === 1) {
                $dates[] = $m[1];
            }
        }
        sort($dates);

        return array_values(array_unique($dates));
    }

    /**
     * @param array<string, mixed> $meta encounter_id, id_efector (opcionales)
     */
    public static function record(
        string $context,
        string $provider,
        ?string $model,
        ?string $tipoModelo,
        string $responseJson,
        int $latencyMs,
        bool $success,
        array $meta = [],
        ?string $promptHash = null
    ): void {
        if (!self::enabled()) {
            return;
        }

        try {
            $usage = self::parseUsage($responseJson);
            $line = [
                'ts' => date('c'),
                'date' => date('Y-m-d'),
                'ctx' => $context,
                'provider' => $provider,
                'model' => $model,
                'tipo' => $tipoModelo,
                'prompt' => $usage['prompt'],
                'cached' => $usage['cached'],
                'output' => $usage['output'],
                'thoughts' => $usage['thoughts'],
                'usd' => round(self::estimateCost($usage), 8),
                'ms' => max(0, $latencyMs),
                'ok' => $success,
                'efector' => (int) ($meta['id_efector'] ?? self::currentEfectorId()),
                'enc' => isset($meta['encounter_id']) ? (int) $meta['encounter_id'] : null,
                'hash' => $promptHash,
            ];
            $json = json_encode($line, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($json === false) {
                return;
            }
            @file_put_contents(self::logPath(), $json . PHP_EOL, FILE_APPEND | LOCK_EX);
        } catch (\Throwable $e) {
            // La telemetría nunca debe romper la llamada de IA.
            Yii::warning('AIUsageRecorder: ' . $e->getMessage(), 'ia-cost');
        }
    }

    /**
     * @return array{prompt:int,cached:int,output:int,thoughts:int}
     */
    public static function parseUsage(string $responseJson): array
    {
        $data = json_decode($responseJson, true);
        $meta = is_array($data) ? ($data['usageMetadata'] ?? null) : null;
        if (!is_array($meta)) {
            return ['prompt' => 0, 'cached' => 0, 'output' => 0, 'thoughts' => 0];
        }

        return [
            'prompt' => (int) ($meta['promptTokenCount'] ?? 0),
            'cached' => (int) ($meta['cachedContentTokenCount'] ?? 0),
            'output' => (int) ($meta['candidatesTokenCount'] ?? 0),
            'thoughts' => (int) ($meta['thoughtsTokenCount'] ?? 0),
        ];
    }

    /**
     * @param array{prompt:int,cached:int,output:int,thoughts:int} $usage
     */
    public static function estimateCost(array $usage): float
    {
        $rates = AICostReferenceMetadata::tarifasUsdPorMillon();
        $billable = max(0, $usage['prompt'] - $usage['cached']);

        return $billable * $rates['input'] / 1_000_000
            + $usage['cached'] * $rates['input_cacheado'] / 1_000_000
            + $usage['output'] * $rates['output'] / 1_000_000;
    }

    private static function currentEfectorId(): int
    {
        try {
            if (Yii::$app->has('user') && method_exists(Yii::$app->user, 'getIdEfector')) {
                return (int) (Yii::$app->user->getIdEfector() ?? 0);
            }
        } catch (\Throwable $e) {
            // Consola o request sin efector.
        }

        return 0;
    }
}
