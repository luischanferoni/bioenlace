<?php

namespace common\components\Services\Actions;

use common\components\IntentCatalog\IntentCatalogService;

/**
 * Atajos de inicio: subconjunto ordenado de **UIs**.
 *
 * Importante:
 * - UI en API = descriptor JSON bajo `/api/v1/ui/<entidad>/<accion>`.
 * - Endpoints de dominio (turnos/agenda/etc.) no son UI; esta lista intenta apuntar a `/ui/`.
 */
final class CommonActionsService
{
    public const DEFAULT_LIMIT = 12;

    /**
     * @return list<array{route: string, name: string, description: string, action_id: string|null}>
     */
    public static function getFormattedForUser(int $userId, int $limit = self::DEFAULT_LIMIT): array
    {
        if ($userId <= 0) {
            return [];
        }

        if ($limit < 1) {
            $limit = self::DEFAULT_LIMIT;
        }
        if ($limit > 50) {
            $limit = 50;
        }

        $available = IntentCatalogService::getAvailableUiForUser($userId, true);
        $ordered = self::prioritizeForCommonShortcuts($available);
        $ordered = array_slice($ordered, 0, $limit);

        $out = [];
        foreach ($ordered as $action) {
            $name = !empty($action['action_name'])
                ? (string) $action['action_name']
                : (string) ($action['display_name'] ?? $action['route'] ?? '');

            $out[] = [
                'route' => (string) ($action['route'] ?? ''),
                'name' => $name,
                'description' => (string) ($action['description'] ?? ''),
                'action_id' => isset($action['action_id']) ? (string) $action['action_id'] : null,
            ];
        }

        return $out;
    }

    /**
     * Prioriza acciones sin parámetros obligatorios (más aptas como atajo), luego el resto; orden estable por nombre.
     *
     * @param array<int, array<string, mixed>> $actions
     * @return array<int, array<string, mixed>>
     */
    private static function prioritizeForCommonShortcuts(array $actions): array
    {
        $noRequired = [];
        $withRequired = [];

        foreach ($actions as $action) {
            $hasRequired = false;
            if (!empty($action['parameters']) && is_array($action['parameters'])) {
                foreach ($action['parameters'] as $p) {
                    if (!empty($p['required'])) {
                        $hasRequired = true;
                        break;
                    }
                }
            }
            if ($hasRequired) {
                $withRequired[] = $action;
            } else {
                $noRequired[] = $action;
            }
        }

        $sortFn = static function (array $a, array $b): int {
            $na = (string) ($a['display_name'] ?? $a['route'] ?? '');
            $nb = (string) ($b['display_name'] ?? $b['route'] ?? '');

            return strcasecmp($na, $nb);
        };

        usort($noRequired, $sortFn);
        usort($withRequired, $sortFn);

        return array_merge($noRequired, $withRequired);
    }
}
