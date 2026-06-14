<?php

namespace common\components\Core\Permission;

use Yii;
use yii\web\IdentityInterface;
use yii\rbac\Item;

/**
 * Caché de permisos/rutas en sesión sobre {@see BioenlaceDbManager} (Yii RBAC), sin webvimark AuthHelper.
 */
final class BioenlaceSessionPermissions
{
    public const SESSION_PREFIX_ROUTES = '__bioenlace_user_routes';

    public const SESSION_PREFIX_ROLES = '__bioenlace_user_roles';

    public const SESSION_PREFIX_PERMISSIONS = '__bioenlace_user_permissions';

    public const SESSION_OWNER_KEY = '__bioenlace_user_routes_owner_id';

    public static function refreshForIdentity(IdentityInterface $identity): void
    {
        if (!Yii::$app->has('session')) {
            return;
        }
        $session = Yii::$app->session;
        try {
            if (!$session->isActive) {
                $session->open();
            }
        } catch (\Throwable $e) {
            return;
        }

        $userId = (int) $identity->getId();
        $built = self::buildForUserId($userId);
        $session->set(self::SESSION_PREFIX_ROUTES, $built['routes']);
        $session->set(self::SESSION_PREFIX_ROLES, $built['roles']);
        $session->set(self::SESSION_PREFIX_PERMISSIONS, $built['permissions']);
        $session->set(self::SESSION_OWNER_KEY, $userId);
    }

    public static function ensureUpToDate(): void
    {
        if (Yii::$app->user->isGuest || Yii::$app->user->identity === null) {
            return;
        }
        $userId = (int) Yii::$app->user->id;
        if (!Yii::$app->has('session')) {
            return;
        }
        $owner = Yii::$app->session->get(self::SESSION_OWNER_KEY);
        if ((int) $owner !== $userId) {
            self::refreshForIdentity(Yii::$app->user->identity);
        }
    }

    /**
     * @return array{routes: array<string, true>, roles: list<string>, permissions: array<string, true>}
     */
    public static function buildForUserId(int $userId): array
    {
        if ($userId <= 0 || !Yii::$app->has('authManager')) {
            return ['routes' => [], 'roles' => [], 'permissions' => []];
        }

        $auth = Yii::$app->authManager;
        $permissions = [];
        foreach ($auth->getPermissionsByUser($userId) as $name => $_item) {
            $permissions[(string) $name] = true;
        }

        $routes = [];
        foreach (array_keys($permissions) as $permName) {
            foreach ($auth->getChildren($permName) as $child) {
                if (self::isRouteItem($child)) {
                    $routes[$child->name] = true;
                }
            }
        }

        $roles = array_keys($auth->getRolesByUser($userId));
        foreach ($roles as $roleName) {
            foreach ($auth->getChildren((string) $roleName) as $child) {
                if (self::isRouteItem($child)) {
                    $routes[$child->name] = true;
                }
            }
        }

        ksort($permissions);
        ksort($routes);
        sort($roles);

        return [
            'routes' => $routes,
            'roles' => $roles,
            'permissions' => $permissions,
        ];
    }

    /**
     * @param object $item yii\rbac\Item
     */
    private static function isRouteItem(object $item): bool
    {
        return isset($item->type) && (int) $item->type === 3;
    }

    public static function unifyRoute(string $route): string
    {
        $route = '/' . ltrim($route, '/');
        if (preg_match('#^/api/v\d+/#', $route) === 1) {
            return preg_replace('#^/api/v\d+/#', '/api/', $route, 1) ?? $route;
        }

        return $route;
    }
}
