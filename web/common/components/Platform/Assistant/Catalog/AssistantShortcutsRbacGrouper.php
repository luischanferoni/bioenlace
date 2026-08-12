<?php

namespace common\components\Platform\Assistant\Catalog;

/**
 * Atajos = intents permitidos por RBAC, agrupados por prefijo del intent_id (familia).
 *
 * No hay listas ni exclusiones por rol en metadata: el admin asigna intent ↔ rol en auth_item_child.
 */
final class AssistantShortcutsRbacGrouper
{
    /**
     * @param list<array<string, mixed>> $availableFlows salida de {@see IntentCatalogService::getAvailableUiForUser}
     *
     * @return list<array{
     *     id: string,
     *     titulo: string,
     *     intent_ids: list<string>,
     *     subgroups: list<array{id: string, titulo: string, intent_ids: list<string>}>
     * }>
     */
    public static function buildCategoryDefinitions(array $availableFlows): array
    {
        /** @var array<string, list<string>> $byGroup */
        $byGroup = [];

        foreach ($availableFlows as $flow) {
            if (!is_array($flow)) {
                continue;
            }
            if (($flow['shortcut_hidden'] ?? false) === true) {
                continue;
            }
            $intentId = trim((string) ($flow['action_id'] ?? ''));
            if ($intentId === '') {
                continue;
            }

            $groupId = self::groupIdFromIntentId($intentId);
            $byGroup[$groupId][] = $intentId;
        }

        if ($byGroup === []) {
            return [];
        }

        $labels = AssistantShortcutGroupLabels::labels();
        $order = AssistantShortcutGroupLabels::groupOrder();
        $sortedGroupIds = self::sortGroupIds(array_keys($byGroup), $order);

        $out = [];
        foreach ($sortedGroupIds as $groupId) {
            $intentIds = array_values(array_unique($byGroup[$groupId] ?? []));
            sort($intentIds, SORT_STRING);
            if ($intentIds === []) {
                continue;
            }
            $out[] = [
                'id' => $groupId,
                'titulo' => $labels[$groupId] ?? self::fallbackGroupTitle($groupId),
                'intent_ids' => $intentIds,
                'subgroups' => [],
            ];
        }

        return $out;
    }

    public static function groupIdFromIntentId(string $intentId): string
    {
        $intentId = trim($intentId);
        $pos = strpos($intentId, '.');

        return $pos === false ? $intentId : substr($intentId, 0, $pos);
    }

    /**
     * @param list<string> $groupIds
     * @param list<string> $preferredOrder
     *
     * @return list<string>
     */
    private static function sortGroupIds(array $groupIds, array $preferredOrder): array
    {
        $rank = [];
        foreach ($preferredOrder as $i => $groupId) {
            $rank[$groupId] = $i;
        }

        usort($groupIds, static function (string $a, string $b) use ($rank): int {
            $ra = $rank[$a] ?? PHP_INT_MAX;
            $rb = $rank[$b] ?? PHP_INT_MAX;
            if ($ra !== $rb) {
                return $ra <=> $rb;
            }

            return strcmp($a, $b);
        });

        return $groupIds;
    }

    private static function fallbackGroupTitle(string $groupId): string
    {
        return ucwords(str_replace(['-', '_'], ' ', $groupId));
    }
}
