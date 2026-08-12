<?php

namespace common\components\Platform\Assistant\Catalog;

/**
 * Arma atajos del asistente: intents permitidos por RBAC + categorías declarativas (v2).
 *
 * v1 (legacy): listas fijas intent_ids en el YAML del catálogo.
 * v2: categorías con reglas de matching; cada intent puede declarar shortcut explícito.
 */
final class AssistantShortcutsAssembler
{
    /**
     * @param list<string> $userRoles
     * @param list<array<string, mixed>> $availableFlows salida filtrada por RBAC ({@see IntentCatalogService})
     *
     * @return list<array{
     *     id: string,
     *     titulo: string,
     *     intent_ids: list<string>,
     *     subgroups: list<array{id: string, titulo: string, intent_ids: list<string>}>
     * }>
     */
    public static function buildCategoryDefinitions(
        array $userRoles,
        array $availableFlows,
        ?string $catalogBasename = null
    ): array {
        $manifest = AssistantShortcutsCatalog::manifest($catalogBasename);
        $version = (int) ($manifest['version'] ?? 1);

        if ($version < 2) {
            return AssistantShortcutsCatalog::categoriesForStaffRoles($userRoles, $catalogBasename);
        }

        return self::assembleV2($userRoles, $availableFlows, $manifest['categories'] ?? []);
    }

    /**
     * @param list<string> $userRoles
     * @param list<array<string, mixed>> $availableFlows
     * @param list<array<string, mixed>> $categoryDefs
     *
     * @return list<array{id: string, titulo: string, intent_ids: list<string>, subgroups: list<array{id: string, titulo: string, intent_ids: list<string>}>}>
     */
    private static function assembleV2(array $userRoles, array $availableFlows, array $categoryDefs): array
    {
        $userRoles = AssistantShortcutsCatalog::normalizeRoleList($userRoles);
        $categoriesById = [];
        foreach ($categoryDefs as $cat) {
            if (!is_array($cat)) {
                continue;
            }
            $id = trim((string) ($cat['id'] ?? ''));
            if ($id === '') {
                continue;
            }
            $categoriesById[$id] = $cat;
        }

        /** @var array<string, array<string, list<array{order: int, intent_id: string}>>> $bucket */
        $bucket = [];

        foreach ($availableFlows as $flow) {
            if (!is_array($flow)) {
                continue;
            }
            $intentId = trim((string) ($flow['action_id'] ?? ''));
            if ($intentId === '') {
                continue;
            }
            if (($flow['shortcut_hidden'] ?? false) === true) {
                continue;
            }

            $placements = $flow['shortcut_placements'] ?? [];
            if (!is_array($placements)) {
                $placements = [];
            }

            if ($placements !== []) {
                foreach ($placements as $placement) {
                    if (!is_array($placement)) {
                        continue;
                    }
                    $categoryId = trim((string) ($placement['category'] ?? ''));
                    if ($categoryId === '' || !isset($categoriesById[$categoryId])) {
                        continue;
                    }
                    if (!self::matchesAudience(array_merge($categoriesById[$categoryId], $placement), $userRoles)) {
                        continue;
                    }
                    $subgroupId = trim((string) ($placement['subgroup'] ?? ''));
                    $order = (int) ($placement['order'] ?? 0);
                    self::addToBucket($bucket, $categoryId, $subgroupId, $intentId, $order);
                }
                continue;
            }

            foreach ($categoryDefs as $cat) {
                if (!is_array($cat)) {
                    continue;
                }
                $categoryId = trim((string) ($cat['id'] ?? ''));
                if ($categoryId === '' || !AssistantShortcutsCatalog::matchesIntentRule($intentId, $cat)) {
                    continue;
                }
                if (!AssistantShortcutsCatalog::matchesStaffAudience($cat, $userRoles)) {
                    break;
                }
                $subgroupId = AssistantShortcutsCatalog::resolveSubgroupForIntent($intentId, $cat);
                if ($subgroupId !== null && $subgroupId !== '') {
                    $sgDef = AssistantShortcutsCatalog::findSubgroup($cat, $subgroupId);
                    if ($sgDef !== null && !AssistantShortcutsCatalog::matchesStaffAudience($sgDef, $userRoles)) {
                        break;
                    }
                }
                self::addToBucket($bucket, $categoryId, $subgroupId ?? '', $intentId, 0);
                break;
            }
        }

        $out = [];
        foreach ($categoryDefs as $cat) {
            if (!is_array($cat)) {
                continue;
            }
            $categoryId = trim((string) ($cat['id'] ?? ''));
            if ($categoryId === '' || !isset($bucket[$categoryId])) {
                continue;
            }
            if (!AssistantShortcutsCatalog::matchesStaffAudience($cat, $userRoles)) {
                continue;
            }

            $catBucket = $bucket[$categoryId];
            $flatIds = self::sortedIntentIds($catBucket[''] ?? []);
            $subgroups = [];
            foreach ($cat['subgroups'] ?? [] as $sg) {
                if (!is_array($sg)) {
                    continue;
                }
                $sgId = trim((string) ($sg['id'] ?? ''));
                if ($sgId === '' || !isset($catBucket[$sgId])) {
                    continue;
                }
                if (!AssistantShortcutsCatalog::matchesStaffAudience($sg, $userRoles)) {
                    continue;
                }
                $sgIntentIds = self::sortedIntentIds($catBucket[$sgId]);
                if ($sgIntentIds === []) {
                    continue;
                }
                $sgTitulo = trim((string) ($sg['titulo'] ?? ''));
                $subgroups[] = [
                    'id' => $sgId,
                    'titulo' => $sgTitulo !== '' ? $sgTitulo : $sgId,
                    'intent_ids' => $sgIntentIds,
                ];
                foreach ($sgIntentIds as $iid) {
                    $flatIds[] = $iid;
                }
            }

            if ($flatIds === [] && $subgroups === []) {
                continue;
            }

            $titulo = trim((string) ($cat['titulo'] ?? ''));
            $out[] = [
                'id' => $categoryId,
                'titulo' => $titulo !== '' ? $titulo : $categoryId,
                'intent_ids' => array_values(array_unique($flatIds)),
                'subgroups' => $subgroups,
            ];
        }

        return $out;
    }

    /**
     * @param array<string, list<array{order: int, intent_id: string}>> $bucket
     */
    private static function addToBucket(array &$bucket, string $categoryId, string $subgroupId, string $intentId, int $order): void
    {
        if (!isset($bucket[$categoryId])) {
            $bucket[$categoryId] = [];
        }
        if (!isset($bucket[$categoryId][$subgroupId])) {
            $bucket[$categoryId][$subgroupId] = [];
        }
        $bucket[$categoryId][$subgroupId][] = [
            'order' => $order,
            'intent_id' => $intentId,
        ];
    }

    /**
     * @param list<array{order: int, intent_id: string}> $entries
     *
     * @return list<string>
     */
    private static function sortedIntentIds(array $entries): array
    {
        usort($entries, static function (array $a, array $b): int {
            $orderCmp = ($a['order'] ?? 0) <=> ($b['order'] ?? 0);
            if ($orderCmp !== 0) {
                return $orderCmp;
            }

            return strcmp((string) ($a['intent_id'] ?? ''), (string) ($b['intent_id'] ?? ''));
        });

        $ids = [];
        foreach ($entries as $entry) {
            $id = trim((string) ($entry['intent_id'] ?? ''));
            if ($id !== '') {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * @param array<string, mixed> $def
     * @param list<string> $userRoles
     */
    private static function matchesAudience(array $def, array $userRoles): bool
    {
        return AssistantShortcutsCatalog::matchesStaffAudience($def, $userRoles);
    }
}
