<?php

namespace common\components\Domain\Scheduling\Service;

use Symfony\Component\Yaml\Yaml;

/**
 * Catálogo declarativo de textos y modalidades para insight staff ({@see metadata/staff_modalidad_insight.yaml}).
 */
final class StaffModalidadInsightCatalogService
{
    private const CATALOG_FILE = 'staff_modalidad_insight.yaml';

    /** @var array<string, mixed>|null */
    private static ?array $cache = null;

    /**
     * @return list<string>
     */
    public function elegibilidadesConInsight(): array
    {
        $raw = self::load()['show_when_elegibilidad'] ?? [];

        return is_array($raw) ? array_values(array_map('strval', $raw)) : [];
    }

    /**
     * @return list<array{code: string, label: string, description: string}>
     */
    public function modalidadesParaElegibilidad(string $elegibilidad): array
    {
        $elegibilidad = trim($elegibilidad);
        $map = self::load()['elegibilidad_modalidades'] ?? [];
        if (!is_array($map) || !isset($map[$elegibilidad]) || !is_array($map[$elegibilidad])) {
            return [];
        }

        $defs = self::load()['modalidades'] ?? [];
        $out = [];
        foreach ($map[$elegibilidad] as $code) {
            $code = trim((string) $code);
            if ($code === '' || !is_array($defs[$code] ?? null)) {
                continue;
            }
            $def = $defs[$code];
            $out[] = [
                'code' => $code,
                'label' => trim((string) ($def['label'] ?? $code)),
                'description' => trim((string) ($def['description'] ?? '')),
            ];
        }

        return $out;
    }

    /**
     * @return array{summary: string, tone: string}|null
     */
    public function mensajeParaElegibilidad(string $elegibilidad): ?array
    {
        $elegibilidad = trim($elegibilidad);
        $messages = self::load()['messages'] ?? [];
        if (!is_array($messages) || !isset($messages[$elegibilidad]) || !is_array($messages[$elegibilidad])) {
            return null;
        }
        $m = $messages[$elegibilidad];

        return [
            'summary' => trim((string) ($m['summary'] ?? '')),
            'tone' => trim((string) ($m['tone'] ?? 'info')),
        ];
    }

    public function footerAgendaNoOnline(): string
    {
        return trim((string) (self::load()['agenda_no_online_footer'] ?? ''));
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

    /** Solo tests. */
    public static function resetCache(): void
    {
        self::$cache = null;
    }
}
