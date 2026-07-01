<?php

namespace common\components\Platform\Core\Service\Actions;

use common\components\Platform\Assistant\Catalog\AssistantShortcutsCatalog;
use common\components\Platform\Assistant\UiActions\AssistantClientOpenEnricher;
use common\components\Platform\Assistant\Catalog\IntentCatalogService;
use common\components\Platform\Core\Product\ClientContextMetadata;

/**
 * Atajos de inicio: subconjunto ordenado de acciones.
 *
 * Importante:
 * - Para flows conversacionales, la acción se ejecuta vía `/api/v1/asistente/enviar` con `action_id`.
 */
final class CommonActionsService
{
    public const DEFAULT_LIMIT = 50;

    /**
     * @return array{actions: list<array{route: string, name: string, description: string, action_id: string|null, client_open?: array, client_interaction?: string}>, categories: list<array{id: string, titulo: string, actions: list<array{route: string, name: string, description: string, action_id: string|null, client_open?: array, client_interaction?: string}>, subgroups?: list<array{id: string, titulo: string, actions: list<array{route: string, name: string, description: string, action_id: string|null, client_open?: array, client_interaction?: string}>}>}>}
     */
    public static function getFormattedForUser(int $userId, int $limit = self::DEFAULT_LIMIT, ?string $appClient = null): array
    {
        if ($limit < 1) {
            $limit = self::DEFAULT_LIMIT;
        }
        if ($limit > 50) {
            $limit = 50;
        }

        $display = self::resolveDisplayOptions($appClient);
        $catalogBasename = $display['catalog_basename'];

        $available = IntentCatalogService::getAvailableUiForUser($userId, true);
        $byId = [];
        foreach ($available as $f) {
            $aid = isset($f['action_id']) ? (string) $f['action_id'] : '';
            if ($aid === '') {
                continue;
            }
            $byId[$aid] = $f;
        }

        $categories = [];
        foreach (AssistantShortcutsCatalog::categories($catalogBasename) as $catDef) {
            $payload = self::buildCategoryPayload($catDef, $byId, $display);
            if ($payload !== null) {
                $categories[] = $payload;
            }
        }

        // Flatten para compat con clientes actuales (si todavía renderizan `actions` plano).
        $flat = [];
        foreach ($categories as $c) {
            foreach (($c['actions'] ?? []) as $a) {
                $flat[] = $a;
            }
        }

        $flat = array_slice($flat, 0, $limit);

        return [
            'actions' => $flat,
            'categories' => $categories,
        ];
    }

    /**
     * @return array{catalog_basename: string, use_yaml_action_name: bool, omit_subgroups: bool}
     */
    private static function resolveDisplayOptions(?string $appClient): array
    {
        if (ClientContextMetadata::isPacienteMobileClient($appClient)) {
            return [
                'catalog_basename' => ClientContextMetadata::pacienteMobileShortcutsCatalogBasename(),
                'use_yaml_action_name' => ClientContextMetadata::pacienteMobileShortcutUseYamlActionName(),
                'omit_subgroups' => ClientContextMetadata::pacienteMobileShortcutOmitSubgroups(),
            ];
        }

        return [
            'catalog_basename' => 'assistant-shortcuts.yaml',
            'use_yaml_action_name' => false,
            'omit_subgroups' => false,
        ];
    }

    /**
     * @param array{id: string, titulo: string, intent_ids: list<string>, subgroups: list<array{id: string, titulo: string, intent_ids: list<string>}>} $catDef
     * @param array<string, array<string, mixed>> $byId
     * @param array{catalog_basename: string, use_yaml_action_name: bool, omit_subgroups: bool} $display
     *
     * @return array{id: string, titulo: string, actions: list<array<string, mixed>>, subgroups?: list<array{id: string, titulo: string, actions: list<array<string, mixed>>}>}|null
     */
    private static function buildCategoryPayload(array $catDef, array $byId, array $display): ?array
    {
        $subgroupsDef = $catDef['subgroups'] ?? [];
        $omitSubgroups = (bool) ($display['omit_subgroups'] ?? false);

        if ($subgroupsDef !== [] && !$omitSubgroups) {
            $subgroups = [];
            $allActions = [];
            foreach ($subgroupsDef as $sgDef) {
                $actions = self::resolveActions($sgDef['intent_ids'], $byId, $display);
                if ($actions === []) {
                    continue;
                }
                $subgroups[] = [
                    'id' => $sgDef['id'],
                    'titulo' => $sgDef['titulo'],
                    'actions' => $actions,
                ];
                foreach ($actions as $action) {
                    $allActions[] = $action;
                }
            }
            if ($subgroups === []) {
                return null;
            }

            return [
                'id' => $catDef['id'],
                'titulo' => $catDef['titulo'],
                'subgroups' => $subgroups,
                'actions' => $allActions,
            ];
        }

        $actions = self::resolveActions($catDef['intent_ids'], $byId, $display);
        if ($actions === [] && $subgroupsDef !== []) {
            foreach ($subgroupsDef as $sgDef) {
                $actions = array_merge(
                    $actions,
                    self::resolveActions($sgDef['intent_ids'], $byId, $display)
                );
            }
        }
        if ($actions === []) {
            return null;
        }

        return [
            'id' => $catDef['id'],
            'titulo' => $catDef['titulo'],
            'actions' => $actions,
        ];
    }

    /**
     * @param list<string> $intentIds
     * @param array<string, array<string, mixed>> $byId
     * @param array{catalog_basename: string, use_yaml_action_name: bool, omit_subgroups: bool} $display
     *
     * @return list<array{route: string, name: string, description: string, action_id: string|null, client_open?: array, client_interaction?: string}>
     */
    private static function resolveActions(array $intentIds, array $byId, array $display): array
    {
        $actions = [];
        foreach ($intentIds as $intentId) {
            $intentId = is_string($intentId) ? trim($intentId) : '';
            if ($intentId === '' || !isset($byId[$intentId])) {
                continue;
            }
            $actions[] = self::flowToActionRow($byId[$intentId], $display);
        }
        usort($actions, static function (array $a, array $b): int {
            return strcasecmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''));
        });

        return $actions;
    }

    /**
     * @param array<string, mixed> $flow
     * @param array{catalog_basename: string, use_yaml_action_name: bool, omit_subgroups: bool} $display
     *
     * @return array{route: string, name: string, description: string, action_id: string|null, client_open?: array, client_interaction?: string}
     */
    private static function flowToActionRow(array $flow, array $display): array
    {
        $aid = isset($flow['action_id']) ? trim((string) $flow['action_id']) : '';
        $useYamlName = (bool) ($display['use_yaml_action_name'] ?? false);
        $baseName = trim((string) ($flow['action_name_base'] ?? ''));
        if ($useYamlName && $baseName !== '') {
            $name = $baseName;
        } elseif (!empty($flow['action_name'])) {
            $name = (string) $flow['action_name'];
        } else {
            $name = (string) ($flow['display_name'] ?? $aid);
        }

        $row = [
            'route' => '',
            'name' => $name,
            'description' => (string) ($flow['description'] ?? ''),
            'action_id' => $aid !== '' ? $aid : null,
        ];

        if ($aid !== '') {
            $row['client_open'] = [
                'kind' => 'intent',
                'intent_id' => $aid,
            ];
            $row['client_interaction'] = 'intent_flow';
        }

        return AssistantClientOpenEnricher::enrich($row);
    }
}
