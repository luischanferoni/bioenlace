<?php

namespace common\components\Core\Permission;

/**
 * Rutas API equivalentes en auth_item (permiso ≠ path HTTP público).
 */
final class ApiRoutePermissionResolver
{
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
