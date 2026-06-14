<?php

namespace common\components\Assistant\UiActions;

use Yii;
use common\components\Core\Service\ClientContextService;
use common\components\Core\Permission\BioenlaceAccessChecker;
use common\components\Core\Permission\BioenlaceSessionPermissions;
use webvimark\modules\UserManagement\models\User;

/**
 * Resuelve el conjunto de rutas permitidas para un usuario o roles sin repetir
 * consultas RBAC pesadas en cada request cuando hay sesión Bioenlace o caché de aplicación.
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
     * Tras refrescar permisos Bioenlace, debe coincidir el dueño de sesión con userId.
     */
    public const SESSION_ROUTES_OWNER_KEY = BioenlaceSessionPermissions::SESSION_OWNER_KEY;

    /**
     * Mapa route => true a partir de roles (misma lógica que el motor de actions/mapping).
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
        $roles = Yii::$app->session->get(BioenlaceSessionPermissions::SESSION_PREFIX_ROLES);
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
        $routes = Yii::$app->session->get(BioenlaceSessionPermissions::SESSION_PREFIX_ROUTES);
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

        if (BioenlaceAccessChecker::isSuperadminUserId($userId)) {
            return null;
        }

        if (!User::findOne($userId)) {
            return [];
        }

        $cache = Yii::$app->cache;
        $cacheKey = self::CACHE_KEY_PREFIX . $userId . ClientContextService::rbacCacheSuffix();
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
                BioenlaceSessionPermissions::ensureUpToDate();
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
                $built = BioenlaceSessionPermissions::buildForUserId($userId);
                foreach (array_keys($built['routes']) as $r) {
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
        $permRoute = self::apiHttpPathToPermissionRoute($actionRoute);
        if ($permRoute !== $actionRoute && isset($map[$permRoute])) {
            return true;
        }
        foreach (array_keys($map) as $allowed) {
            if (!is_string($allowed)) {
                continue;
            }
            if (RbacRoute::isSubRoute($allowed, $actionRoute)) {
                return true;
            }
            if ($permRoute !== $actionRoute && RbacRoute::isSubRoute($allowed, $permRoute)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Convierte el path HTTP versionado (`/api/v1/...`) al formato de ruta usado en permisos (`/api/...`).
     */
    public static function apiHttpPathToPermissionRoute(string $path): string
    {
        $p = '/' . ltrim($path, '/');
        if (preg_match('#^/api/v\d+/#', $p)) {
            return preg_replace('#^/api/v\d+/#', '/api/', $p, 1);
        }
        return $p;
    }

    /**
     * Rutas candidatas para {@see BioenlaceAccessChecker::userHasRoute} en controladores web.
     *
     * En webvimark a veces el permiso figura con o sin prefijo de módulo (`/foo/bar` vs `/frontend/foo/bar`)
     * (según cómo se generó/registró la ruta). Hay que probar ambas para no filtrar UIs nativas en vano.
     *
     * @return list<string>
     */
    public static function nativeFrontendWebRbacRouteCandidates(string $controller, string $action): array
    {
        $controller = trim($controller, '/');
        $action = trim($action, '/');
        if ($controller === '' || $action === '') {
            return [];
        }

        $candidates = $action === 'index'
            ? [
                '/' . $controller . '/index',
                '/frontend/' . $controller . '/index',
                '/' . $controller,
                '/frontend/' . $controller,
            ]
            : [
                '/' . $controller . '/' . $action,
                '/frontend/' . $controller . '/' . $action,
            ];

        $uniq = [];
        foreach ($candidates as $c) {
            $c = '/' . ltrim((string) $c, '/');
            $uniq[$c] = true;
        }

        return array_keys($uniq);
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
     * Marcar dueño de sesión de permisos Bioenlace (tras refreshForIdentity).
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
