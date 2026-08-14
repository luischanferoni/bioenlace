<?php

namespace common\components\Platform\Assistant\Catalog;

use common\components\Platform\Core\Permission\IntentManifestIndex;

/**
 * Metadata de atajo en manifiestos de intent ({@see intents/*.yaml} → clave `shortcut` / `shortcuts`).
 *
 * Atajos = intents del asistente con UI genérica embebible. No lanzan pantallas nativas.
 */
final class IntentShortcutMetadata
{
    /**
     * True si algún open_ui del intent abre una pantalla nativa (web path / mobile screen).
     */
    public static function opensNativeUi(string $intentId): bool
    {
        $intentId = trim($intentId);
        if ($intentId === '') {
            return false;
        }

        $meta = IntentManifestIndex::get($intentId);
        if ($meta === null) {
            return false;
        }

        foreach ($meta['open_ui_steps'] ?? [] as $step) {
            if (!is_array($step)) {
                continue;
            }
            $actionId = trim((string) ($step['action_id'] ?? ''));
            if ($actionId === '') {
                continue;
            }
            $clientOpen = UiActionCatalogProviderRegistry::clientOpenForActionId($actionId);
            if (is_array($clientOpen) && trim((string) ($clientOpen['kind'] ?? '')) === 'native') {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $manifest
     *
     * @return list<array{
     *     category: string,
     *     subgroup: string,
     *     order: int,
     *     staff_roles: list<string>,
     *     exclude_staff_roles: list<string>
     * }>
     */
    public static function explicitPlacements(array $manifest): array
    {
        if (self::isHidden($manifest)) {
            return [];
        }

        if (isset($manifest['shortcuts']) && is_array($manifest['shortcuts'])) {
            return self::parsePlacementList($manifest['shortcuts']);
        }

        if (isset($manifest['shortcut']) && is_array($manifest['shortcut'])) {
            return self::parsePlacementList([$manifest['shortcut']]);
        }

        return [];
    }

    /**
     * @param array<string, mixed> $manifest
     */
    public static function isHidden(array $manifest): bool
    {
        if (isset($manifest['shortcut']) && is_array($manifest['shortcut'])) {
            if (($manifest['shortcut']['hidden'] ?? false) === true) {
                return true;
            }
        }

        return ($manifest['shortcut_hidden'] ?? false) === true;
    }

    /**
     * @param mixed $raw
     *
     * @return list<array{
     *     category: string,
     *     subgroup: string,
     *     order: int,
     *     staff_roles: list<string>,
     *     exclude_staff_roles: list<string>
     * }>
     */
    private static function parsePlacementList($raw): array
    {
        if (!is_array($raw)) {
            return [];
        }

        $out = [];
        foreach ($raw as $item) {
            if (!is_array($item)) {
                continue;
            }
            if (($item['hidden'] ?? false) === true) {
                continue;
            }
            $category = trim((string) ($item['category'] ?? ''));
            if ($category === '') {
                continue;
            }
            $out[] = [
                'category' => $category,
                'subgroup' => trim((string) ($item['subgroup'] ?? '')),
                'order' => (int) ($item['order'] ?? 0),
                'staff_roles' => self::parseRoleList($item['staff_roles'] ?? []),
                'exclude_staff_roles' => self::parseRoleList($item['exclude_staff_roles'] ?? []),
            ];
        }

        return $out;
    }

    /**
     * @param mixed $raw
     *
     * @return list<string>
     */
    private static function parseRoleList($raw): array
    {
        if (!is_array($raw)) {
            return [];
        }
        $roles = [];
        foreach ($raw as $role) {
            if (is_string($role) && trim($role) !== '') {
                $roles[] = trim($role);
            }
        }

        return $roles;
    }
}
