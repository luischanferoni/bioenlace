<?php

namespace common\components\Platform\Assistant\Context;

use common\components\Platform\Assistant\Metadata\AssistantMetadataLoader;
use common\components\Platform\Core\Product\ProductMetadataPaths;

/**
 * Áreas top-level del HIS expuestas al preprocess (lista cerrada).
 *
 * Descripciones de prompt: {@see catalog/context-his-areas.yaml}.
 */
final class AssistantContextHISArea
{
    public const APPOINTMENTS = 'appointments';
    public const ENCOUNTERS = 'encounters';
    public const CLINICAL_RECORD = 'clinical_record';
    public const DIAGNOSTICS = 'diagnostics';
    public const MEDICATION = 'medication';
    public const REPRESENTATION = 'representation';
    public const COVERAGE = 'coverage';
    public const PRODUCT = 'product';
    public const GEO_RESOURCES = 'geo_resources';

    /** @var list<string> */
    private const AREA_IDS = [
        self::APPOINTMENTS,
        self::ENCOUNTERS,
        self::CLINICAL_RECORD,
        self::DIAGNOSTICS,
        self::MEDICATION,
        self::REPRESENTATION,
        self::COVERAGE,
        self::PRODUCT,
        self::GEO_RESOURCES,
    ];

    /** @var array<string, string>|null */
    private static ?array $descriptionCache = null;

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

        $order = array_flip(self::productPriorityOrder());
        usort(
            $valid,
            static fn (string $a, string $b): int => ($order[$a] ?? 999) <=> ($order[$b] ?? 999)
        );

        return $valid;
    }

    /**
     * @return list<string>
     */
    private static function productPriorityOrder(): array
    {
        return self::AREA_IDS;
    }

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return self::AREA_IDS;
    }

    public static function isValid(string $id): bool
    {
        return in_array(trim($id), self::AREA_IDS, true);
    }

    public static function description(string $id): string
    {
        $id = trim($id);
        if ($id === '') {
            return '';
        }

        return self::descriptionsCatalog()[$id] ?? '';
    }

    /**
     * Lista `- id — descripción` para placeholders de prompt (valores desde metadata YAML).
     */
    public static function listForPrompt(): string
    {
        $lines = [];
        foreach (self::AREA_IDS as $id) {
            $desc = self::description($id);
            $lines[] = $desc !== '' ? '- ' . $id . ' — ' . $desc : '- ' . $id;
        }

        return implode("\n", $lines);
    }

    /**
     * @return array<string, string>
     */
    private static function descriptionsCatalog(): array
    {
        if (self::$descriptionCache !== null) {
            return self::$descriptionCache;
        }

        $config = AssistantMetadataLoader::load(ProductMetadataPaths::contextHisAreasCatalogFile());
        $raw = $config['areas'] ?? [];
        if (!is_array($raw)) {
            return self::$descriptionCache = [];
        }

        $out = [];
        foreach ($raw as $id => $desc) {
            $id = trim((string) $id);
            if ($id === '' || !self::isValid($id)) {
                continue;
            }
            $out[$id] = trim((string) $desc);
        }

        return self::$descriptionCache = $out;
    }

    public static function resetCacheForTests(): void
    {
        self::$descriptionCache = null;
    }
}
