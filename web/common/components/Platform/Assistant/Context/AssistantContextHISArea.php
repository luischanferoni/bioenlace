<?php

namespace common\components\Platform\Assistant\Context;

use common\components\Platform\Assistant\Metadata\AssistantMetadataLoader;
use common\components\Platform\Core\Product\ProductMetadataPaths;

/**
 * Áreas de contexto del asistente (lista cerrada).
 *
 * Source of truth de **textos**: {@see catalog/context-his-areas.yaml}.
 * Los ids de dominio espejan carpetas `metadata/bioenlace/<dominio>/`;
 * `product` y `geo_resources` son solo-contexto (sin intents).
 */
final class AssistantContextHISArea
{
    public const SCHEDULING = 'scheduling';
    public const CLINICAL = 'clinical';
    public const PERSON = 'person';
    public const ORGANIZATION = 'organization';
    public const PLATFORM = 'platform';
    public const PRODUCT = 'product';
    public const GEO_RESOURCES = 'geo_resources';

    /** @var list<string>|null */
    private static ?array $idsCache = null;

    /** @var array<string, string>|null */
    private static ?array $descriptionCache = null;

    /** @var array<string, true>|null */
    private static ?array $contextOnlyCache = null;

    /**
     * @return list<string>
     */
    public static function sortByProductPriority(array $areas): array
    {
        $valid = [];
        foreach ($areas as $area) {
            if (!is_string($area)) {
                continue;
            }
            $area = trim($area);
            if ($area !== '' && self::isValid($area) && !in_array($area, $valid, true)) {
                $valid[] = $area;
            }
        }
        if ($valid === []) {
            return [];
        }

        $order = array_flip(self::all());
        usort(
            $valid,
            static fn (string $a, string $b): int => ($order[$a] ?? 999) <=> ($order[$b] ?? 999)
        );

        return $valid;
    }

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        self::loadCatalog();

        return self::$idsCache ?? [];
    }

    /**
     * Áreas con intents (excluye solo-contexto).
     *
     * @return list<string>
     */
    public static function domainAreas(): array
    {
        $out = [];
        foreach (self::all() as $id) {
            if (!self::isContextOnly($id)) {
                $out[] = $id;
            }
        }

        return $out;
    }

    public static function isValid(string $id): bool
    {
        return in_array(trim($id), self::all(), true);
    }

    public static function isContextOnly(string $id): bool
    {
        $id = trim($id);
        if ($id === '') {
            return false;
        }
        self::loadCatalog();

        return isset(self::$contextOnlyCache[$id]);
    }

    public static function description(string $id): string
    {
        $id = trim($id);
        if ($id === '') {
            return '';
        }
        self::loadCatalog();

        return self::$descriptionCache[$id] ?? '';
    }

    /**
     * Lista `- id — descripción` para placeholders de prompt.
     */
    public static function listForPrompt(): string
    {
        $lines = [];
        foreach (self::all() as $id) {
            $desc = self::description($id);
            $lines[] = $desc !== '' ? '- ' . $id . ' — ' . $desc : '- ' . $id;
        }

        return implode("\n", $lines);
    }

    public static function resetCacheForTests(): void
    {
        self::$idsCache = null;
        self::$descriptionCache = null;
        self::$contextOnlyCache = null;
    }

    private static function loadCatalog(): void
    {
        if (self::$idsCache !== null) {
            return;
        }

        $config = AssistantMetadataLoader::load(ProductMetadataPaths::contextHisAreasCatalogFile());
        $raw = $config['areas'] ?? [];
        if (!is_array($raw)) {
            self::$idsCache = [];
            self::$descriptionCache = [];
            self::$contextOnlyCache = [];

            return;
        }

        $ids = [];
        $descriptions = [];
        $contextOnly = [];
        foreach ($raw as $id => $value) {
            $id = trim((string) $id);
            if ($id === '') {
                continue;
            }
            $ids[] = $id;
            if (is_array($value)) {
                $descriptions[$id] = trim((string) ($value['text'] ?? $value['description'] ?? ''));
                if (!empty($value['context_only'])) {
                    $contextOnly[$id] = true;
                }
            } else {
                $descriptions[$id] = trim((string) $value);
            }
        }

        self::$idsCache = $ids;
        self::$descriptionCache = $descriptions;
        self::$contextOnlyCache = $contextOnly;
    }
}
