<?php

namespace common\components\Platform\Assistant\UiActions;

use common\components\Platform\Core\Permission\BioenlaceAccessChecker;
use common\components\Platform\Core\Permission\FlowStepAccessService;
use common\components\Platform\Ui\ApiV1HttpRoute;

/**
 * Construye acciones tipo open_route hacia UI JSON, filtradas por intents autorizados.
 */
final class ChatApiActionBuilder
{
    /**
     * @param array<string, mixed> $action salida de {@see ActionMappingService}
     * @return array{type: string, title: string, route: string, params: array}
     */
    public static function discoveredActionToOpenRoute(array $action, string $title): array
    {
        $route = self::httpRouteForDiscoveredAction($action);

        return [
            'type' => 'open_route',
            'title' => $title,
            'route' => $route,
            'params' => [],
        ];
    }

    /**
     * Primera acción permitida que matchee palabras clave (catálogo intent).
     *
     * @param list<string> $keywords
     * @return array<string, mixed>|null
     */
    public static function firstMatchingApiAction(?int $userId, array $keywords): ?array
    {
        if (!$userId) {
            return null;
        }
        $keywords = array_map('strtolower', array_filter($keywords));
        if ($keywords === []) {
            return null;
        }

        $actions = ActionMappingService::getAvailableActionsForUser($userId);
        $best = null;
        $bestScore = 0;
        foreach ($actions as $action) {
            $haystack = strtolower(
                ($action['display_name'] ?? '')
                . ' ' . ($action['action_id'] ?? '')
                . ' ' . ($action['route'] ?? '')
                . ' ' . implode(' ', $action['tags'] ?? [])
                . ' ' . ($action['entity'] ?? '')
            );
            $score = 0;
            foreach ($keywords as $kw) {
                if ($kw !== '' && str_contains($haystack, $kw)) {
                    $score += 10;
                }
            }
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $action;
            }
        }

        return $bestScore > 0 ? $best : null;
    }

    public static function userCanOpenApiRoute(?int $userId, string $route, ?string $flowIntentId = null): bool
    {
        if (!$userId) {
            return false;
        }
        $userId = (int) $userId;
        if (BioenlaceAccessChecker::isSuperadminUserId($userId)) {
            return true;
        }

        $route = '/' . ltrim($route, '/');
        if (BioenlaceAccessChecker::userCanApiRoute($userId, $route)) {
            return true;
        }

        return (new FlowStepAccessService())->canAccessViaParentIntent($userId, $route, $flowIntentId);
    }

    /**
     * @param array<string, mixed> $action
     */
    private static function httpRouteForDiscoveredAction(array $action): string
    {
        $permissionRoute = trim((string) ($action['route'] ?? ''));
        if ($permissionRoute !== '') {
            return ApiV1HttpRoute::normalize($permissionRoute);
        }

        $controller = trim(strtolower((string) ($action['controller'] ?? '')));
        $actionName = trim(strtolower((string) ($action['action'] ?? '')));
        if ($controller === '' || $actionName === '') {
            return '';
        }

        return '/api/v1/' . rawurlencode($controller) . '/' . rawurlencode($actionName);
    }
}
