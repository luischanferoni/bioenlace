<?php

namespace common\components\Platform\Assistant\Catalog;

use Symfony\Component\Yaml\Yaml;
use Yii;

/**
 * Manifiesto de categorías de atajos ({@see assistant-shortcuts.yaml}).
 *
 * v1: listas fijas de intent_id.
 * v2: reglas de matching + RBAC; ver {@see AssistantShortcutsAssembler}.
 */
final class AssistantShortcutsCatalog
{
    /** @var array<string, list<array<string, mixed>>> */
    private static array $categoriesByCatalog = [];

    /** @var array<string, array{version: int, categories: list<array<string, mixed>>}> */
    private static array $manifestByCatalog = [];

    /**
     * @return array{version: int, categories: list<array<string, mixed>>}
     */
    public static function manifest(?string $catalogBasename = null): array
    {
        $catalogBasename = self::normalizeCatalogBasename($catalogBasename);
        if (isset(self::$manifestByCatalog[$catalogBasename])) {
            return self::$manifestByCatalog[$catalogBasename];
        }

        self::$manifestByCatalog[$catalogBasename] = [
            'version' => 1,
            'categories' => [],
        ];

        $path = \common\components\Platform\Core\Product\ProductMetadataPaths::assistantShortcutsFile($catalogBasename);
        if (!is_file($path)) {
            return self::$manifestByCatalog[$catalogBasename];
        }

        try {
            $data = Yaml::parseFile($path);
        } catch (\Throwable $e) {
            Yii::warning('AssistantShortcutsCatalog: YAML inválido: ' . $e->getMessage(), __METHOD__);

            return self::$manifestByCatalog[$catalogBasename];
        }

        if (!is_array($data)) {
            return self::$manifestByCatalog[$catalogBasename];
        }

        $categories = [];
        foreach ($data['categories'] ?? [] as $cat) {
            $parsed = self::parseCategoryDef($cat);
            if ($parsed !== null) {
                $categories[] = $parsed;
            }
        }

        self::$manifestByCatalog[$catalogBasename] = [
            'version' => (int) ($data['version'] ?? 1),
            'categories' => $categories,
        ];

        return self::$manifestByCatalog[$catalogBasename];
    }

    /**
     * v1: categorías con intent_ids estáticos.
     *
     * @return list<array{id: string, titulo: string, intent_ids: list<string>, subgroups: list<array{id: string, titulo: string, intent_ids: list<string>}>, staff_roles: list<string>, exclude_staff_roles: list<string>}>
     */
    public static function categories(?string $catalogBasename = null): array
    {
        $catalogBasename = self::normalizeCatalogBasename($catalogBasename);
        if (isset(self::$categoriesByCatalog[$catalogBasename])) {
            return self::$categoriesByCatalog[$catalogBasename];
        }

        $manifest = self::manifest($catalogBasename);
        if (($manifest['version'] ?? 1) >= 2) {
            self::$categoriesByCatalog[$catalogBasename] = [];

            return self::$categoriesByCatalog[$catalogBasename];
        }

        self::$categoriesByCatalog[$catalogBasename] = $manifest['categories'];

        return self::$categoriesByCatalog[$catalogBasename];
    }

    /**
     * @param list<string> $userRoles
     *
     * @return list<array{id: string, titulo: string, intent_ids: list<string>, subgroups: list<array{id: string, titulo: string, intent_ids: list<string>}>, staff_roles: list<string>, exclude_staff_roles: list<string>}>
     */
    public static function categoriesForStaffRoles(array $userRoles, ?string $catalogBasename = null): array
    {
        $userRoles = self::normalizeRoleList($userRoles);
        $out = [];
        foreach (self::categories($catalogBasename) as $cat) {
            if (!self::matchesStaffAudience($cat, $userRoles)) {
                continue;
            }
            $subgroups = [];
            foreach ($cat['subgroups'] ?? [] as $sg) {
                if (self::matchesStaffAudience($sg, $userRoles)) {
                    $subgroups[] = $sg;
                }
            }
            if ($subgroups === [] && ($cat['intent_ids'] ?? []) === []) {
                continue;
            }
            $cat['subgroups'] = $subgroups;
            $out[] = $cat;
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $catDef
     */
    public static function matchesIntentRule(string $intentId, array $catDef): bool
    {
        $intentId = trim($intentId);
        if ($intentId === '') {
            return false;
        }

        foreach ($catDef['exclude_intent_ids'] ?? [] as $excluded) {
            if (is_string($excluded) && $excluded !== '' && $intentId === trim($excluded)) {
                return false;
            }
        }

        foreach ($catDef['intent_ids'] ?? [] as $id) {
            if ($intentId === $id) {
                return true;
            }
        }
        foreach ($catDef['intent_prefixes'] ?? [] as $prefix) {
            if (is_string($prefix) && $prefix !== '' && str_starts_with($intentId, $prefix)) {
                return true;
            }
        }
        foreach ($catDef['intent_id_contains'] ?? [] as $needle) {
            if (is_string($needle) && $needle !== '' && str_contains($intentId, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $catDef
     */
    public static function resolveSubgroupForIntent(string $intentId, array $catDef): ?string
    {
        $subgroups = $catDef['subgroups'] ?? [];
        if ($subgroups === []) {
            return null;
        }

        $defaultId = null;
        foreach ($subgroups as $sg) {
            if (!is_array($sg)) {
                continue;
            }
            $sgId = trim((string) ($sg['id'] ?? ''));
            if ($sgId === '') {
                continue;
            }
            if (($sg['default'] ?? false) === true) {
                $defaultId = $sgId;
            }
            foreach ($sg['intent_ids'] ?? [] as $id) {
                if ($intentId === $id) {
                    return $sgId;
                }
            }
            foreach ($sg['intent_prefixes'] ?? [] as $prefix) {
                if (is_string($prefix) && $prefix !== '' && str_starts_with($intentId, $prefix)) {
                    return $sgId;
                }
            }
            foreach ($sg['intent_suffixes'] ?? [] as $suffix) {
                if (!is_string($suffix) || $suffix === '') {
                    continue;
                }
                if (str_ends_with($intentId, $suffix) || str_contains($intentId, $suffix)) {
                    return $sgId;
                }
            }
        }

        return $defaultId;
    }

    /**
     * @param array<string, mixed> $catDef
     *
     * @return array<string, mixed>|null
     */
    public static function findSubgroup(array $catDef, string $subgroupId): ?array
    {
        foreach ($catDef['subgroups'] ?? [] as $sg) {
            if (is_array($sg) && ($sg['id'] ?? '') === $subgroupId) {
                return $sg;
            }
        }

        return null;
    }

    /**
     * @param array{staff_roles?: list<string>, exclude_staff_roles?: list<string>} $def
     * @param list<string> $userRoles
     */
    public static function matchesStaffAudience(array $def, array $userRoles): bool
    {
        $exclude = $def['exclude_staff_roles'] ?? [];
        if ($exclude !== []) {
            foreach ($exclude as $role) {
                if (in_array($role, $userRoles, true)) {
                    return false;
                }
            }
        }

        $include = $def['staff_roles'] ?? [];
        if ($include === []) {
            return true;
        }

        foreach ($include as $role) {
            if (in_array($role, $userRoles, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<string> $roles
     *
     * @return list<string>
     */
    public static function normalizeRoleList(array $roles): array
    {
        return self::parseRoleList($roles);
    }

    /**
     * @param mixed $cat
     *
     * @return array<string, mixed>|null
     */
    private static function parseCategoryDef($cat): ?array
    {
        if (!is_array($cat)) {
            return null;
        }
        $id = trim((string) ($cat['id'] ?? ''));
        if ($id === '') {
            return null;
        }

        $titulo = trim((string) ($cat['titulo'] ?? ''));
        $subgroups = self::parseSubgroups($cat['subgroups'] ?? []);
        $intentIds = self::parseStringList($cat['intent_ids'] ?? []);
        $intentPrefixes = self::parseStringList($cat['intent_prefixes'] ?? []);
        $intentContains = self::parseStringList($cat['intent_id_contains'] ?? []);

        if ($subgroups === [] && $intentIds === [] && $intentPrefixes === [] && $intentContains === []) {
            return null;
        }

        return [
            'id' => $id,
            'titulo' => $titulo !== '' ? $titulo : $id,
            'order' => (int) ($cat['order'] ?? 0),
            'intent_ids' => $intentIds,
            'intent_prefixes' => $intentPrefixes,
            'intent_id_contains' => $intentContains,
            'exclude_intent_ids' => self::parseStringList($cat['exclude_intent_ids'] ?? []),
            'subgroups' => $subgroups,
            'staff_roles' => self::parseRoleList($cat['staff_roles'] ?? []),
            'exclude_staff_roles' => self::parseRoleList($cat['exclude_staff_roles'] ?? []),
        ];
    }

    /**
     * @param mixed $raw
     *
     * @return list<array<string, mixed>>
     */
    private static function parseSubgroups($raw): array
    {
        if (!is_array($raw)) {
            return [];
        }

        $subgroups = [];
        foreach ($raw as $sg) {
            if (!is_array($sg)) {
                continue;
            }
            $sgId = trim((string) ($sg['id'] ?? ''));
            if ($sgId === '') {
                continue;
            }

            $intentIds = self::parseStringList($sg['intent_ids'] ?? []);
            $intentPrefixes = self::parseStringList($sg['intent_prefixes'] ?? []);
            $intentSuffixes = self::parseStringList($sg['intent_suffixes'] ?? []);
            $isDefault = ($sg['default'] ?? false) === true;
            $staffRoles = self::parseRoleList($sg['staff_roles'] ?? []);
            $excludeStaffRoles = self::parseRoleList($sg['exclude_staff_roles'] ?? []);
            $hasAudience = $staffRoles !== [] || $excludeStaffRoles !== [];

            if ($intentIds === [] && $intentPrefixes === [] && $intentSuffixes === [] && !$isDefault && !$hasAudience) {
                continue;
            }

            $sgTitulo = trim((string) ($sg['titulo'] ?? ''));
            $subgroups[] = [
                'id' => $sgId,
                'titulo' => $sgTitulo !== '' ? $sgTitulo : $sgId,
                'default' => $isDefault,
                'intent_ids' => $intentIds,
                'intent_prefixes' => $intentPrefixes,
                'intent_suffixes' => $intentSuffixes,
                'staff_roles' => $staffRoles,
                'exclude_staff_roles' => $excludeStaffRoles,
            ];
        }

        return $subgroups;
    }

    /**
     * @param mixed $raw
     *
     * @return list<string>
     */
    private static function parseRoleList($raw): array
    {
        return self::parseStringList($raw);
    }

    /**
     * @param mixed $raw
     *
     * @return list<string>
     */
    private static function parseStringList($raw): array
    {
        if (!is_array($raw)) {
            return [];
        }
        $values = [];
        foreach ($raw as $value) {
            if (is_string($value) && trim($value) !== '') {
                $values[] = trim($value);
            }
        }

        return $values;
    }

    private static function normalizeCatalogBasename(?string $catalogBasename): string
    {
        $catalogBasename = trim((string) ($catalogBasename ?? ''));

        return $catalogBasename !== '' ? $catalogBasename : 'assistant-shortcuts.yaml';
    }

    public static function resetCacheForTests(): void
    {
        self::$categoriesByCatalog = [];
        self::$manifestByCatalog = [];
    }
}
