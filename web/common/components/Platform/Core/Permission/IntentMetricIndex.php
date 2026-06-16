<?php

namespace common\components\Platform\Core\Permission;

use Symfony\Component\Yaml\Yaml;

/**
 * Enlace declarativo intent_id ↔ metric_id (métricas DataAccess migradas).
 */
final class IntentMetricIndex
{
    /** @var array<string, string>|null metric_id => intent_id */
    private static ?array $intentByMetricId = null;

    /** @var array<string, string>|null intent_id => metric_id */
    private static ?array $metricByIntentId = null;

    public static function resetCache(): void
    {
        self::$intentByMetricId = null;
        self::$metricByIntentId = null;
        IntentManifestIndex::resetCache();
    }

    public static function intentForMetric(string $metricId): ?string
    {
        self::ensureBuilt();
        $metricId = trim($metricId);

        return self::$intentByMetricId[$metricId] ?? null;
    }

    public static function metricForIntent(string $intentId): ?string
    {
        self::ensureBuilt();
        $intentId = trim($intentId);

        return self::$metricByIntentId[$intentId] ?? null;
    }

    /**
     * @return array<string, string> metric_id => intent_id
     */
    public static function allBindings(): array
    {
        self::ensureBuilt();

        return self::$intentByMetricId ?? [];
    }

    private static function ensureBuilt(): void
    {
        if (self::$intentByMetricId !== null) {
            return;
        }

        self::$intentByMetricId = [];
        self::$metricByIntentId = [];

        foreach (IntentManifestIndex::all() as $intentId => $meta) {
            $metricId = trim((string) ($meta['metric_id'] ?? ''));
            if ($metricId === '') {
                $path = trim((string) ($meta['path'] ?? ''));
                if ($path !== '' && is_file($path)) {
                    try {
                        $parsed = Yaml::parseFile($path);
                        if (is_array($parsed)) {
                            $metricId = trim((string) ($parsed['metric_id'] ?? ''));
                        }
                    } catch (\Throwable $e) {
                        $metricId = '';
                    }
                }
            }
            if ($metricId === '') {
                continue;
            }
            if (isset(self::$intentByMetricId[$metricId])) {
                continue;
            }
            self::$intentByMetricId[$metricId] = $intentId;
            self::$metricByIntentId[$intentId] = $metricId;
        }
    }
}
