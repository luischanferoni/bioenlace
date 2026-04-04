<?php

namespace common\components\Actions;

use Yii;
use webvimark\modules\UserManagement\components\AuthHelper;
use webvimark\modules\UserManagement\models\rbacDB\Route as RbacRoute;
use webvimark\modules\UserManagement\models\User;

/**
 * Resuelve el conjunto de rutas permitidas para un usuario o roles sin repetir
 * consultas RBAC pesadas en cada request cuando hay sesión webvimark (__userRoutes)
 * o caché de aplicación.
 */
final class AllowedRoutesResolver
{
    /**
     * Versión de clave de caché: si cambia la lógica del mapa, se ignoran entradas viejas.
     */
    public const CACHE_KEY_PREFIX = 'allowed_routes_map_u_v2_';

    public const CACHE_DURATION = 1800;

    public const LOG_CATEGORY = 'allowed-routes-resolver';

    /**
     * Tras {@see AuthHelper::updatePermissions}, debe coincidir con el usuario cuyas rutas están en
     * {@see AuthHelper::SESSION_PREFIX_ROUTES}; si no, no se reutiliza la sesión (evita JWT ≠ dueño de __userRoutes).
     */
    public const SESSION_ROUTES_OWNER_KEY = '__bioenlace_user_routes_owner_id';

    /**
     * Mapa route => true a partir de roles (misma lógica que Actions\UniversalQueryAgent).
     *
     * @param string[] $roles
     * @return array<string, true>
     */
    public static function getTargetRoutesMapForRoles(array $roles, bool $useCache = true): array
    {
        $useCache = ActionCatalogSettings::shouldUseCache($useCache);

        $roles = array_values(array_filter(array_map('strval', $roles)));
        sort($roles);
        $cacheKey = 'target_routes_roles_' . md5(implode(',', $roles));
        $cache = Yii::$app->cache;
        if ($useCache && $cache) {
            $cached = $cache->get($cacheKey);
            if ($cached !== false && is_array($cached)) {
                Yii::info('AllowedRoutesResolver: role map cache hit', self::LOG_CATEGORY);
                return $cached;
            }
        }

        $authManager = Yii::$app->authManager;
        $targetRoutes = [];
        foreach ($roles as $role) {
            try {
                $roleObj = $authManager->getRole($role);
                if ($roleObj) {
                    $permissions = $authManager->getPermissionsByRole($role);
                    foreach ($permissions as $permission) {
                        $children = $authManager->getChildren($permission->name);
                        foreach ($children as $item) {
                            if ((int) $item->type === 3) {
                                $targetRoutes[$item->name] = true;
                            }
                        }
                    }
                }
            } catch (\Throwable $e) {
                Yii::warning("AllowedRoutesResolver roles: {$e->getMessage()}", self::LOG_CATEGORY);
            }
        }

        if ($useCache && $cache) {
            $cache->set($cacheKey, $targetRoutes, self::CACHE_DURATION);
        }
        return $targetRoutes;
    }

    /**
     * Roles en sesión webvimark (si existe).
     *
     * @return string[]|null
     */
    public static function getSessionRoleNames(): ?array
    {
        if (!Yii::$app->has('session') || Yii::$app->session->getIsActive() === false) {
            return null;
        }
        $roles = Yii::$app->session->get(AuthHelper::SESSION_PREFIX_ROLES);
        return is_array($roles) ? $roles : null;
    }

    /**
     * Rutas en sesión webvimark tras ensurePermissionsUpToDate.
     *
     * @return string[]|null
     */
    public static function getSessionUserRoutes(): ?array
    {
        if (!Yii::$app->has('session') || Yii::$app->session->getIsActive() === false) {
            return null;
        }
        $routes = Yii::$app->session->get(AuthHelper::SESSION_PREFIX_ROUTES);
        return is_array($routes) ? $routes : null;
    }

    /**
     * Mismo conjunto de roles (orden independiente).
     *
     * @param string[] $roles
     */
    public static function sessionRolesMatch(array $roles): bool
    {
        if (Yii::$app->user->isGuest) {
            return false;
        }
        $sessionRoles = self::getSessionRoleNames();
        if ($sessionRoles === null) {
            return false;
        }
        $a = array_map('strval', $roles);
        $b = array_map('strval', $sessionRoles);
        sort($a);
        sort($b);
        return $a === $b;
    }

    /**
     * Mapa de rutas para un usuario.
     * null = superadmin (sin filtro por mapa; el llamador trata "todas").
     * [] = sin rutas explícitas.
     *
     * @return array<string, true>|null
     */
    public static function getTargetRoutesMapForUserId(int $userId, bool $useAppCache = true): ?array
    {
        $useAppCache = ActionCatalogSettings::shouldUseCache($useAppCache);

        $user = User::findOne($userId);
        if (!$user) {
            return [];
        }
        if ((int) $user->superadmin === 1) {
            return null;
        }

        $cache = Yii::$app->cache;
        $cacheKey = self::CACHE_KEY_PREFIX . $userId;
        if ($useAppCache && $cache) {
            $cached = $cache->get($cacheKey);
            if ($cached !== false) {
                if ($cached === '__empty__') {
                    return [];
                }
                if (is_array($cached)) {
                    Yii::info("AllowedRoutesResolver: app cache hit user {$userId}", self::LOG_CATEGORY);
                    return $cached;
                }
            }
        }

        $map = [];
        $fromSession = false;
        if (!Yii::$app->user->isGuest && (int) Yii::$app->user->id === $userId) {
            try {
                AuthHelper::ensurePermissionsUpToDate();
            } catch (\Throwable $e) {
                Yii::debug('ensurePermissionsUpToDate: ' . $e->getMessage(), self::LOG_CATEGORY);
            }
            $sessionRoutes = self::getSessionUserRoutes();
            if ($sessionRoutes !== null && $sessionRoutes !== []) {
                foreach ($sessionRoutes as $r) {
                    $r = is_string($r) ? '/' . ltrim($r, '/') : '';
                    if ($r !== '' && $r !== '/') {
                        $map[$r] = true;
                    }
                }
                $fromSession = !empty($map);
                if ($fromSession) {
                    $routesOwner = Yii::$app->session->get(self::SESSION_ROUTES_OWNER_KEY);
                    if ($routesOwner === null || (int) $routesOwner !== $userId) {
                        Yii::info(
                            "AllowedRoutesResolver: ignorando __userRoutes (owner sesión no coincide con userId {$userId})",
                            self::LOG_CATEGORY
                        );
                        $fromSession = false;
                        $map = [];
                    } else {
                        Yii::info("AllowedRoutesResolver: session routes hit user {$userId} count=" . count($map), self::LOG_CATEGORY);
                    }
                }
            }
        }

        if (!$fromSession) {
            try {
                $list = RbacRoute::getUserRoutes($userId, true);
                foreach ($list as $r) {
                    $r = is_string($r) ? '/' . ltrim($r, '/') : '';
                    if ($r !== '' && $r !== '/') {
                        $map[$r] = true;
                    }
                }
                Yii::info("AllowedRoutesResolver: built from Route::getUserRoutes user {$userId} count=" . count($map), self::LOG_CATEGORY);
            } catch (\Throwable $e) {
                Yii::warning('getUserRoutes: ' . $e->getMessage(), self::LOG_CATEGORY);
            }
        }

        if ($useAppCache && $cache) {
            $cache->set($cacheKey, $map === [] ? '__empty__' : $map, self::CACHE_DURATION);
        }

        return $map;
    }

    /**
     * ¿La ruta de acción está permitida según el mapa?
     * $map null => superadmin, siempre true (salvo que el llamador restrinja).
     */
    public static function routeAllowedByMap(?string $actionRoute, ?array $map): bool
    {
        if ($actionRoute === null || $actionRoute === '') {
            return false;
        }
        $actionRoute = '/' . ltrim($actionRoute, '/');
        if ($map === null) {
            return true;
        }
        if (isset($map[$actionRoute])) {
            return true;
        }
        foreach (array_keys($map) as $allowed) {
            if (!is_string($allowed)) {
                continue;
            }
            if (RbacRoute::isSubRoute($allowed, $actionRoute)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Invalida caché de rutas para un usuario (p.ej. tras cambio de rol).
     */
    public static function invalidateUserCache(int $userId): void
    {
        if (Yii::$app->cache) {
            Yii::$app->cache->delete(self::CACHE_KEY_PREFIX . $userId);
        }
    }

    /**
     * Marcar que {@see AuthHelper::SESSION_PREFIX_ROUTES} corresponde a este usuario (llamar tras updatePermissions).
     */
    public static function markSessionRoutesOwner(int $userId): void
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
            Yii::debug('markSessionRoutesOwner: no se pudo abrir sesión: ' . $e->getMessage(), self::LOG_CATEGORY);

            return;
        }
        $session->set(self::SESSION_ROUTES_OWNER_KEY, $userId);
    }
}
