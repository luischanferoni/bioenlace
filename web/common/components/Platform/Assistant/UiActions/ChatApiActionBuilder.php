<?php

namespace common\components\Platform\Assistant\UiActions;

/**
 * Construye acciones tipo open_route hacia **UI JSON** (descriptores JSON bajo `/api/v1/<entidad>/<accion>`),
 * filtradas por RBAC vía ActionMappingService.
 *
 * Nota de terminología:
 * - “UI en API” = descriptor JSON (ruta `/api/v1/<entidad>/<accion>`).
 * - Endpoints de negocio no son “UI”; son APIs de negocio.
 */
final class ChatApiActionBuilder
{
    private static function uiDescriptorRouteForDiscoveredAction(array $action): string
    {
        $controller = isset($action['controller']) ? (string) $action['controller'] : '';
        $actionName = isset($action['action']) ? (string) $action['action'] : '';
        $controller = trim(strtolower($controller));
        $actionName = trim(strtolower($actionName));
        if ($controller === '' || $actionName === '') {
            return '';
        }

        return '/api/v1/' . rawurlencode($controller) . '/' . rawurlencode($actionName);
    }

    /** Prefijos de ruta considerados API para el chat */
    private static function isApiRoute(string $route): bool
    {
        $r = strtolower($route);

        return str_contains($r, '/api/')
            || str_contains($r, 'api/v1/')
            || preg_match('#\b(api|post|get|put|delete)\s+api/#i', $route) === 1;
    }

    /**
     * @param array<string, mixed> $action descubierta
     * @return array{type: string, title: string, route: string, params: array, method?: string}
     */
    public static function discoveredActionToOpenRoute(array $action, string $title): array
    {
        // Descriptores JSON viven en `views/json/...`, pero se exponen como endpoints normales `/api/v1/<entidad>/<accion>`.
        $route = self::uiDescriptorRouteForDiscoveredAction($action);
        if ($route === '') {
            // Fallback conservador: ruta descubierta (puede ser dominio)
            $route = isset($action['route']) ? (string) $action['route'] : '';
            $route = preg_replace('#^(GET|POST|PUT|PATCH|DELETE|OPTIONS)\s+#i', '', trim($route));
            $route = '/' . ltrim($route, '/');
        }
        $method = 'GET';

        $out = [
            'type' => 'open_route',
            'title' => $title,
            'route' => $route,
            'params' => [],
        ];
        return $out;
    }

    /**
     * Primera acción API descubierta que matchee palabras clave (permisos ya filtrados por usuario).
     *
     * @param string[] $keywords
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
            $route = (string) ($action['route'] ?? '');
            if (!self::isApiRoute($route)) {
                continue;
            }
            $haystack = strtolower(
                ($action['display_name'] ?? '')
                . ' ' . $route . ' '
                . implode(' ', $action['tags'] ?? [])
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

    public static function userCanOpenApiRoute(?int $userId, string $route): bool
    {
        if (!$userId) {
            return false;
        }
        $map = AllowedRoutesResolver::getTargetRoutesMapForUserId($userId, true);
        if ($map === null) {
            return true;
        }
        $route = '/' . ltrim($route, '/');

        return AllowedRoutesResolver::routeAllowedByMap($route, $map);
    }

    // Nota: el motor Assistant debe ser agnóstico de dominio.
    // Builders de CTAs específicos deben vivir fuera de este feature.
}
