<?php

namespace common\components\Platform\Core\Permission;

use Yii;
use yii\db\Query;

/**
 * Rutas de acceso libre (login, error, permiso común guest) sin webvimark Route.
 */
final class RbacFreeRouteChecker
{
    /**
     * @param string $route Ruta RBAC normalizada o cruda
     */
    public static function isFreeAccess(string $route): bool
    {
        $route = BioenlaceSessionPermissions::unifyRoute('/' . ltrim($route, '/'));

        foreach (self::systemRoutes() as $systemRoute) {
            if ($route === $systemRoute) {
                return true;
            }
        }

        return in_array($route, self::commonPermissionRoutes(), true);
    }

    /**
     * @return list<string>
     */
    private static function systemRoutes(): array
    {
        $errorAction = (string) (Yii::$app->errorHandler->errorAction ?? 'site/error');
        $loginUrl = Yii::$app->user->loginUrl ?? ['/auth/login'];
        $loginRoute = is_array($loginUrl) ? '/' . ltrim((string) ($loginUrl[0] ?? 'auth/login'), '/') : (string) $loginUrl;

        return array_values(array_unique(array_filter([
            BioenlaceSessionPermissions::unifyRoute($loginRoute),
            '/auth/login',
            '/auth/logout',
            '/user-management/auth/logout',
            BioenlaceSessionPermissions::unifyRoute('/' . ltrim($errorAction, '/')),
            '/user-management/auth/registration',
        ])));
    }

    /**
     * @return list<string>
     */
    private static function commonPermissionRoutes(): array
    {
        if (!Yii::$app->has('cache')) {
            return self::loadCommonPermissionRoutes();
        }

        $cacheKey = '__bioenlace_common_routes';
        $cached = Yii::$app->cache->get($cacheKey);
        if (is_array($cached)) {
            return $cached;
        }

        $routes = self::loadCommonPermissionRoutes();
        Yii::$app->cache->set($cacheKey, $routes, 3600);

        return $routes;
    }

    /**
     * @return list<string>
     */
    private static function loadCommonPermissionRoutes(): array
    {
        $parent = self::commonPermissionName();
        if ($parent === '') {
            return [];
        }

        $children = (new Query())
            ->select(['child'])
            ->from('{{%auth_item_child}}')
            ->where(['parent' => $parent])
            ->column();

        if ($children === []) {
            return [];
        }

        $allRouteNames = (new Query())
            ->select(['name'])
            ->from('{{%auth_item}}')
            ->where(['type' => 3])
            ->column();

        $routeMap = [];
        foreach ($allRouteNames as $name) {
            if (is_string($name) && $name !== '') {
                $routeMap[$name] = $name;
            }
        }

        $expanded = [];
        foreach ($children as $child) {
            if (!is_string($child) || $child === '') {
                continue;
            }
            $expanded[] = BioenlaceSessionPermissions::unifyRoute($child);
            foreach (self::expandSubRoutes((string) $child, $routeMap) as $sub) {
                $expanded[] = BioenlaceSessionPermissions::unifyRoute($sub);
            }
        }

        return array_values(array_unique($expanded));
    }

    private static function commonPermissionName(): string
    {
        if (Yii::$app->has('user-management')) {
            $module = Yii::$app->getModule('user-management');
            if ($module !== null && !empty($module->commonPermissionName)) {
                return (string) $module->commonPermissionName;
            }
        }

        return (string) (Yii::$app->params['rbacCommonPermissionName'] ?? 'commonPermission');
    }

    /**
     * @param array<string, string> $allRoutes
     * @return list<string>
     */
    private static function expandSubRoutes(string $givenRoute, array $allRoutes): array
    {
        $result = [];
        foreach (array_keys($allRoutes) as $route) {
            if (RbacRoute::isSubRoute($givenRoute, $route)) {
                $result[] = $route;
            }
        }

        return $result;
    }
}
