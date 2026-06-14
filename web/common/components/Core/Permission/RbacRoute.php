<?php

namespace common\components\Core\Permission;

/**
 * Utilidades de rutas RBAC (reemplazo de webvimark {@code Route::isSubRoute}).
 */
final class RbacRoute
{
    /**
     * ¿{@see $candidate} coincide con {@see $route} exacta o con prefijo wildcard
     * cuando {@see $route} termina en {@code /*}?
     */
    public static function isSubRoute(string $route, string $candidate): bool
    {
        $route = '/' . ltrim($route, '/');
        $candidate = '/' . ltrim($candidate, '/');

        if ($route === $candidate) {
            return true;
        }

        if (str_ends_with($route, '/*')) {
            $prefix = rtrim($route, '*');

            return strncmp($candidate, $prefix, strlen($prefix)) === 0;
        }

        return false;
    }
}
