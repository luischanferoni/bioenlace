<?php

namespace common\components\Domain\Scheduling\Service;

use Symfony\Component\Yaml\Yaml;

/**
 * Copy y plantillas de UI para oferta de slots/días ({@see metadata/turno_slot_offer_ui.yaml}).
 */
final class TurnoSlotOfferUiCatalogService
{
    private const CATALOG_FILE = 'turno_slot_offer_ui.yaml';

    /** @var array<string, mixed>|null */
    private static ?array $cache = null;

    public function labelDiaRelativo(int $diffDays): ?string
    {
        $map = self::load()['day_relative_labels'] ?? [];
        if (!is_array($map)) {
            return null;
        }
        $key = (string) $diffDays;
        if (!isset($map[$key])) {
            return null;
        }
        $label = trim((string) $map[$key]);

        return $label !== '' ? $label : null;
    }

    public function nombreDiaSemana(int $weekday): string
    {
        $map = self::load()['weekdays'] ?? [];
        if (!is_array($map)) {
            return '';
        }
        $label = trim((string) ($map[(string) $weekday] ?? $map[$weekday] ?? ''));

        return $label;
    }

    /**
     * Plantilla de título de franja; placeholders: {day}.
     */
    public function tituloFranja(string $franjaCode, string $dayHeading): string
    {
        $map = self::load()['franja_title_templates'] ?? [];
        $tpl = is_array($map) ? trim((string) ($map[$franjaCode] ?? '')) : '';
        if ($tpl === '') {
            return $dayHeading;
        }

        return str_replace('{day}', $dayHeading, $tpl);
    }

    /**
     * @return array<string, mixed>
     */
    public function listBlockDefaults(): array
    {
        $defaults = self::load()['list_block_defaults'] ?? [];

        return is_array($defaults) ? $defaults : [];
    }

    public static function resetCacheForTests(): void
    {
        self::$cache = null;
    }

    /**
     * @return array<string, mixed>
     */
    private static function load(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }
        $path = dirname(__DIR__) . '/metadata/' . self::CATALOG_FILE;
        if (!is_file($path)) {
            self::$cache = [];

            return self::$cache;
        }
        $parsed = Yaml::parseFile($path);
        self::$cache = is_array($parsed) ? $parsed : [];

        return self::$cache;
    }
}
