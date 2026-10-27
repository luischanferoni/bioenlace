<?php

namespace common\components\Platform\Assistant\Catalog;

use common\components\Platform\Core\Product\ProductMetadataPaths;
use Symfony\Component\Yaml\Yaml;

/**
 * Agrupamiento explícito de atajos (categorías y subgrupos) desde metadata.
 *
 * Complementa el agrupamiento automático por prefijo de intent_id: lo declarado acá
 * gana, y el resto se sigue agrupando por familia en {@see AssistantShortcutsRbacGrouper}.
 *
 * Fuente: {@see ProductMetadataPaths::assistantShortcutsFile()} (assistant-shortcut-grouping.yaml).
 */
final class AssistantShortcutGrouping
{
    private const BASENAME = 'assistant-shortcut-grouping.yaml';

    /** @var list<array{id: string, titulo: string, intent_ids: list<string>, subgroups: list<array{id: string, titulo: string, intent_ids: list<string>}>}>|null */
    private static ?array $cache = null;

    public static function resetCacheForTests(): void
    {
        self::$cache = null;
    }

    /**
     * Categorías ordenadas por `order` y luego por id.
     *
     * @return list<array{
     *   id: string,
     *   titulo: string,
     *   intent_ids: list<string>,
     *   subgroups: list<array{id: string, titulo: string, intent_ids: list<string>}>
     * }>
     */
    public static function categories(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        $path = ProductMetadataPaths::assistantShortcutsFile(self::BASENAME);
        if (!is_file($path)) {
            self::$cache = [];

            return self::$cache;
        }

        $parsed = Yaml::parseFile($path);
        $rawCategories = is_array($parsed) && isset($parsed['categories']) && is_array($parsed['categories'])
            ? $parsed['categories']
            : [];

        $rows = [];
        $index = 0;
        foreach ($rawCategories as $raw) {
            if (!is_array($raw)) {
                continue;
            }
            $id = trim((string) ($raw['id'] ?? ''));
            if ($id === '') {
                continue;
            }

            $subgroups = [];
            foreach (($raw['subgroups'] ?? []) as $rawSubgroup) {
                if (!is_array($rawSubgroup)) {
                    continue;
                }
                $subgroupId = trim((string) ($rawSubgroup['id'] ?? ''));
                if ($subgroupId === '') {
                    continue;
                }
                $subgroups[] = [
                    'id' => $subgroupId,
                    'titulo' => trim((string) ($rawSubgroup['titulo'] ?? $subgroupId)),
                    'intent_ids' => self::stringList($rawSubgroup['intents'] ?? []),
                ];
            }

            $rows[] = [
                'order' => (int) ($raw['order'] ?? (100 + $index)),
                'index' => $index++,
                'id' => $id,
                'titulo' => trim((string) ($raw['titulo'] ?? $id)),
                'intent_ids' => self::stringList($raw['intents'] ?? []),
                'subgroups' => $subgroups,
            ];
        }

        usort($rows, static function (array $a, array $b): int {
            if ($a['order'] !== $b['order']) {
                return $a['order'] <=> $b['order'];
            }

            return $a['index'] <=> $b['index'];
        });

        self::$cache = [];
        foreach ($rows as $row) {
            unset($row['order'], $row['index']);
            self::$cache[] = $row;
        }

        return self::$cache;
    }

    /**
     * @param mixed $raw
     * @return list<string>
     */
    private static function stringList($raw): array
    {
        if (!is_array($raw)) {
            return [];
        }

        $out = [];
        foreach ($raw as $item) {
            $value = trim((string) $item);
            if ($value !== '' && !in_array($value, $out, true)) {
                $out[] = $value;
            }
        }

        return $out;
    }
}
