<?php

namespace common\components\Domain\Scheduling\Service;

use Symfony\Component\Yaml\Yaml;

/**
 * Labels y ventana de KPIs async ({@see metadata/consulta_async_indicadores.yaml}).
 */
final class ConsultaAsyncIndicadoresCatalogService
{
    private const CATALOG_FILE = 'consulta_async_indicadores.yaml';

    /** @var array<string, mixed>|null */
    private static ?array $cache = null;

    public function tituloSeccion(): string
    {
        $block = self::load()['section'] ?? [];

        return trim((string) (is_array($block) ? ($block['title'] ?? '') : ''));
    }

    public function ventanaDias(): int
    {
        $block = self::load()['section'] ?? [];
        $n = is_array($block) ? ($block['ventana_dias'] ?? 30) : 30;

        return max(1, (int) $n);
    }

    /**
     * @return array<string, string>
     */
    public function kpiLabels(): array
    {
        $map = self::load()['kpi_labels'] ?? [];

        return is_array($map) ? array_map(static fn ($v) => trim((string) $v), $map) : [];
    }

    public function label(string $key, string $fallback = ''): string
    {
        $labels = $this->kpiLabels();
        $label = trim((string) ($labels[$key] ?? ''));

        return $label !== '' ? $label : ($fallback !== '' ? $fallback : $key);
    }

    /**
     * @return array<string, mixed>
     */
    private static function load(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }
        $path = __DIR__ . '/../metadata/' . self::CATALOG_FILE;
        if (!is_file($path)) {
            self::$cache = [];

            return self::$cache;
        }
        $parsed = Yaml::parseFile($path);
        self::$cache = is_array($parsed) ? $parsed : [];

        return self::$cache;
    }

    public static function resetCache(): void
    {
        self::$cache = null;
    }
}
