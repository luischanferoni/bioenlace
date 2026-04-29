<?php

namespace common\components\Services\Actions;

 use common\components\Assistant\UiActions\AssistantClientOpenEnricher;
 use common\components\Assistant\Catalog\IntentCatalogService;
use common\components\Assistant\UiActions\ActionMappingService;

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
     * @return array{actions: list<array{route: string, name: string, description: string, action_id: string|null, client_open?: array, client_interaction?: string}>, categories: list<array{id: string, titulo: string, actions: list<array{route: string, name: string, description: string, action_id: string|null, client_open?: array, client_interaction?: string}>}>}
     */
    public static function getFormattedForUser(int $userId, int $limit = self::DEFAULT_LIMIT): array
    {
        if ($limit < 1) {
            $limit = self::DEFAULT_LIMIT;
        }
        if ($limit > 50) {
            $limit = 50;
        }

        $available = IntentCatalogService::getAvailableUiForUser($userId, true);
        $flowsAllowed = self::filterFlowsByRbac($available, $userId);
        $byId = [];
        foreach ($flowsAllowed as $f) {
            $aid = isset($f['action_id']) ? (string) $f['action_id'] : '';
            if ($aid === '') {
                continue;
            }
            $byId[$aid] = $f;
        }

        $categories = [];
        foreach (self::flowCategoriesDefinition() as $cat) {
            $catId = isset($cat['id']) ? (string) $cat['id'] : '';
            $title = isset($cat['titulo']) ? (string) $cat['titulo'] : '';
            $models = isset($cat['models']) && is_array($cat['models']) ? $cat['models'] : [];
            $actions = [];
            foreach ($models as $intentId) {
                $intentId = is_string($intentId) ? trim($intentId) : '';
                if ($intentId === '' || !isset($byId[$intentId])) {
                    continue;
                }
                $actions[] = self::flowToActionRow($byId[$intentId]);
            }
            if ($actions === []) {
                continue;
            }
            usort($actions, static function (array $a, array $b): int {
                return strcasecmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''));
            });
            $categories[] = [
                'id' => $catId !== '' ? $catId : strtolower(preg_replace('/\s+/', '-', $title) ?? 'categoria'),
                'titulo' => $title !== '' ? $title : 'Atajos',
                'actions' => $actions,
            ];
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
     * Definición manual de categorías de atajos (flows).
     *
     * @return list<array{id: string, titulo: string, models: list<string>}>
     */
    private static function flowCategoriesDefinition(): array
    {
        return [
            [
                'id' => 'rrhh_agenda',
                'titulo' => 'Recursos Humanos, Agenda y Condición laboral',
                'models' => [
                    'agenda.crear-agenda-flow',
                    'agenda.editar-agenda-flow',
                ],
            ],
            [
                'id' => 'turnos',
                'titulo' => 'Turnos',
                'models' => [
                    'turnos.crear-como-paciente',
                ],
            ],
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $flows
     * @return array<int, array<string, mixed>>
     */
    private static function filterFlowsByRbac(array $flows, int $userId): array
    {
        $out = [];
        foreach ($flows as $flow) {
            if (!is_array($flow)) {
                continue;
            }
            $aid = isset($flow['action_id']) ? trim((string) $flow['action_id']) : '';
            if ($aid === '') {
                continue;
            }
            $rbacRoute = isset($flow['rbac_route']) ? trim((string) $flow['rbac_route']) : '';
            if ($rbacRoute === '') {
                // Si falta rbac_route, negar por seguridad (evita exponer flows sin control).
                continue;
            }
            if (!ActionMappingService::userIdCanAccessRoute($userId, $rbacRoute)) {
                continue;
            }
            $out[] = $flow;
        }
        return $out;
    }

    /**
     * @param array<string, mixed> $flow
     * @return array{route: string, name: string, description: string, action_id: string|null, client_open?: array, client_interaction?: string}
     */
    private static function flowToActionRow(array $flow): array
    {
        $aid = isset($flow['action_id']) ? trim((string) $flow['action_id']) : '';
        $name = !empty($flow['action_name'])
            ? (string) $flow['action_name']
            : (string) ($flow['display_name'] ?? $aid);

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
