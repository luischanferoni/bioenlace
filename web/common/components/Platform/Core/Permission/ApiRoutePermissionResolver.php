<?php

namespace common\components\Platform\Core\Permission;

/**
 * Rutas API equivalentes en auth_item (permiso ≠ path HTTP público).
 */
final class ApiRoutePermissionResolver
{
    /**
     * Path HTTP de la petición (pathInfo) → ruta RBAC `/api/...` (sin segmento de versión).
     */
    public static function permissionRouteFromHttpPath(string $pathInfo): string
    {
        $path = '/' . ltrim(trim($pathInfo), '/');
        if ($path === '/' || $path === '') {
            return '';
        }
        if (preg_match('#^/api/v\d+/#', $path) === 1) {
            return preg_replace('#^/api/v\d+/#', '/api/', $path, 1) ?? $path;
        }
        if (str_starts_with($path, '/api/')) {
            return $path;
        }

        return '';
    }

    /**
     * Rutas RBAC candidatas: URL HTTP pública y ruta del controlador (aliases urlManager).
     *
     * @return list<string>
     */
    public static function checkedRoutesForAction(string $pathInfo, string $actionUniqueId): array
    {
        $uniq = [];
        $fromHttp = self::permissionRouteFromHttpPath($pathInfo);
        if ($fromHttp !== '') {
            $uniq[$fromHttp] = true;
        }

        $parts = explode('/', $actionUniqueId);
        if (!empty($parts) && $parts[0] === 'v1') {
            array_shift($parts);
        }
        $fromController = '/api/' . implode('/', $parts);
        if ($fromController !== '/api/' && $fromController !== '') {
            $uniq[$fromController] = true;
        }

        return array_keys($uniq);
    }

    /**
     * Ruta RBAC principal a chequear (primera de {@see checkedRoutesForAction}).
     */
    public static function resolveCheckedRouteForAction(string $pathInfo, string $actionUniqueId): string
    {
        $routes = self::checkedRoutesForAction($pathInfo, $actionUniqueId);

        return $routes[0] ?? '';
    }

    /**
     * @return list<string>
     */
    public static function candidates(string $route): array
    {
        $route = '/' . ltrim($route, '/');
        $out = [$route];

        if (preg_match('#^/api/v\d+/#', $route) === 1) {
            $out[] = preg_replace('#^/api/v\d+/#', '/api/', $route, 1);
        }

        if (preg_match('#/index$#', $route) === 1) {
            $out[] = preg_replace('#/index$#', '', $route);
        }

        $uniq = [];
        foreach ($out as $c) {
            if (is_string($c) && $c !== '') {
                $uniq[$c] = true;
            }
        }

        return array_keys($uniq);
    }
}
