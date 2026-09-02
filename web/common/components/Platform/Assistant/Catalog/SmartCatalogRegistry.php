<?php

namespace common\components\Platform\Assistant\Catalog;

use common\components\Platform\Assistant\Metadata\AssistantMetadataLoader;
use common\components\Platform\Core\Product\ProductMetadataPaths;

/**
 * Carga {@see assistant/catalog/smart-catalog.yaml}.
 */
final class SmartCatalogRegistry
{
    /** @var list<SmartCatalogEntry>|null */
    private static ?array $entriesCache = null;

    public static function resetCacheForTests(): void
    {
        self::$entriesCache = null;
    }

    /**
     * @return list<SmartCatalogEntry>
     */
    public static function entries(): array
    {
        if (self::$entriesCache !== null) {
            return self::$entriesCache;
        }

        $config = AssistantMetadataLoader::load(ProductMetadataPaths::smartCatalogFile());
        $raw = $config['entries'] ?? [];
        if (!is_array($raw)) {
            return self::$entriesCache = [];
        }

        $out = [];
        foreach ($raw as $row) {
            if (!is_array($row)) {
                continue;
            }
            $entry = self::parseEntry($row);
            if ($entry !== null) {
                $out[] = $entry;
            }
        }

        return self::$entriesCache = $out;
    }

    public static function findById(string $id): ?SmartCatalogEntry
    {
        $id = trim($id);
        if ($id === '') {
            return null;
        }
        foreach (self::entries() as $entry) {
            if ($entry->id === $id) {
                return $entry;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $row
     */
    private static function parseEntry(array $row): ?SmartCatalogEntry
    {
        $id = trim((string) ($row['id'] ?? ''));
        if ($id === '') {
            return null;
        }

        $matchOnly = (bool) ($row['match_only'] ?? false);
        $toolType = trim((string) ($row['tool_type'] ?? ''));
        $toolRef = trim((string) ($row['tool_ref'] ?? ''));
        if (!$matchOnly && $toolType === '' && $toolRef === '') {
            return null;
        }
        if (!$matchOnly && $toolType !== '' && $toolRef === '') {
            return null;
        }

        $routingResult = trim((string) ($row['routing_result'] ?? 'incompletas'));
        if ($routingResult === '') {
            $routingResult = 'incompletas';
        }

        $triggers = is_array($row['triggers'] ?? null) ? $row['triggers'] : [];

        return new SmartCatalogEntry(
            $id,
            $matchOnly,
            $toolType,
            $toolRef,
            self::buildToolId($toolType, $toolRef),
            $routingResult,
            max(0, (int) ($row['priority'] ?? 50)),
            self::normalizeTriggerList($triggers['tags'] ?? []),
            self::normalizeTriggerList($triggers['context_areas'] ?? []),
            self::normalizeTriggerList($triggers['phrases'] ?? []),
            self::normalizeTriggerList($triggers['keywords'] ?? []),
            self::normalizeTriggerList($row['required_anchors'] ?? []),
            self::normalizeTriggerList($row['requires_data_fields'] ?? []),
            trim((string) ($row['response_template'] ?? '')),
            trim((string) ($row['cta_intent_id'] ?? '')),
        );
    }

    private static function buildToolId(string $toolType, string $toolRef): string
    {
        if ($toolRef === '') {
            return '';
        }
        if ($toolType === 'intent') {
            return $toolRef;
        }

        return $toolType . ':' . $toolRef;
    }

    /**
     * @param mixed $raw
     * @return list<string>
     */
    private static function normalizeTriggerList(mixed $raw): array
    {
        if (!is_array($raw)) {
            return [];
        }
        $out = [];
        foreach ($raw as $item) {
            if (!is_string($item)) {
                continue;
            }
            $item = self::fold(trim($item));
            if ($item !== '' && !in_array($item, $out, true)) {
                $out[] = $item;
            }
        }

        return $out;
    }

    private static function fold(string $value): string
    {
        return mb_strtolower($value, 'UTF-8');
    }
}
