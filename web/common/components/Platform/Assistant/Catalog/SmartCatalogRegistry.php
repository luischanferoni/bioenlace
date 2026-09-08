<?php

namespace common\components\Platform\Assistant\Catalog;

use common\components\Platform\Assistant\Metadata\AssistantMetadataLoader;
use common\components\Platform\Assistant\Preprocess\PreprocessRoutingHintCatalog;
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

    /**
     * Tags únicos declarados en triggers del smart-catalog.
     *
     * @return list<string>
     */
    public static function allTriggerTags(): array
    {
        $tags = [];
        foreach (self::entries() as $entry) {
            foreach ($entry->triggerTags as $tag) {
                if ($tag !== '' && !in_array($tag, $tags, true)) {
                    $tags[] = $tag;
                }
            }
        }
        sort($tags);

        return $tags;
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

        $routingResult = trim((string) ($row['routing_result'] ?? PreprocessRoutingHintCatalog::INCOMPLETAS));
        if ($routingResult === '' || !PreprocessRoutingHintCatalog::isValid($routingResult)) {
            $routingResult = PreprocessRoutingHintCatalog::INCOMPLETAS;
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
            self::normalizeCtaIntentIds($row),
        );
    }

    /**
     * @param array<string, mixed> $row
     * @return list<string>
     */
    private static function normalizeCtaIntentIds(array $row): array
    {
        $out = [];
        $list = $row['cta_intent_ids'] ?? null;
        if (is_array($list)) {
            foreach ($list as $id) {
                if (!is_string($id)) {
                    continue;
                }
                $id = trim($id);
                if ($id !== '' && !in_array($id, $out, true)) {
                    $out[] = $id;
                }
            }
        }
        $single = trim((string) ($row['cta_intent_id'] ?? ''));
        if ($single !== '' && !in_array($single, $out, true)) {
            $out[] = $single;
        }

        return $out;
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
