<?php

namespace common\components\Actions;

use Yii;
use webvimark\modules\UserManagement\models\User;
use webvimark\modules\UserManagement\models\rbacDB\Route;

/**
 * Servicio para mapear acciones descubiertas a formato consumible por IA
 * y filtrar por roles y permisos del usuario
 */
class ActionMappingService
{
    /**
     * Cache key para acciones por rol
     */
    public const CACHE_KEY_PREFIX = 'actions_for_role_v2_';
    public const CACHE_DURATION = 1800; // 30 minutos

    /**
     * Obtener acciones disponibles para un usuario.
     * Filtra por roles y permisos del usuario.
     * @param int|null $userId ID del usuario (si es null no se devuelven acciones)
     * @param bool $useCache Usar cache
     * @return array
     */
    public static function getAvailableActionsForUser($userId = null, $useCache = true)
    {
        if (!$userId) {
            return [];
        }

        // Obtener roles del usuario
        $user = User::findOne($userId);
        if (!$user) {
            return [];
        }

        $roles = self::getUserRoles($user);

        // Cache key basado en userId y roles (para evitar conflictos entre usuarios)
        $cacheKey = self::CACHE_KEY_PREFIX . $userId . '_' . md5(implode(',', $roles));

        $cache = Yii::$app->cache;
        if ($useCache && $cache) {
            $cached = $cache->get($cacheKey);
            if ($cached !== false) {
                return $cached;
            }
        }

        // Obtener todas las acciones descubiertas
        $allActions = ActionDiscoveryService::discoverAllActions($useCache);

        // Log para debugging
        Yii::info("ActionMappingService: Total acciones descubiertas: " . count($allActions) . " para usuario: {$userId}", 'action-mapping');

        $routeMap = null;
        if ((int) $user->superadmin !== 1) {
            $routeMap = AllowedRoutesResolver::getTargetRoutesMapForUserId((int) $user->id, $useCache);
        }

        $availableActions = [];

        foreach ($allActions as $action) {
            if (self::userCanAccessRoute($user, $action['route'], $routeMap)) {
                $availableActions[] = $action;
            }
        }

        $availableActions = self::dedupeActionsByRoute($availableActions);

        // Log para debugging
        Yii::info("ActionMappingService: Acciones disponibles después de filtrar: " . count($availableActions) . " para usuario: {$userId}", 'action-mapping');

        // Guardar en cache
        if ($cache) {
            $cache->set($cacheKey, $availableActions, self::CACHE_DURATION);
        }

        return $availableActions;
    }

    /**
     * Evita duplicados (misma ruta de permiso) por descubrimiento en web + API v1.
     *
     * @param array $actions
     * @return array
     */
    private static function dedupeActionsByRoute(array $actions): array
    {
        $seen = [];
        $out = [];
        foreach ($actions as $action) {
            $r = isset($action['route']) ? '/' . ltrim((string) $action['route'], '/') : '';
            if ($r === '' || $r === '/') {
                $out[] = $action;
                continue;
            }
            if (isset($seen[$r])) {
                continue;
            }
            $seen[$r] = true;
            $out[] = $action;
        }

        return $out;
    }

    /**
     * Obtener roles del usuario
     * @param User $user
     * @return array
     */
    private static function getUserRoles($user)
    {
        $roles = [];

        if ($user->superadmin == 1) {
            $roles[] = 'superadmin';
        }

        // Obtener roles asignados
        $authManager = Yii::$app->authManager;
        $assignedRoles = $authManager->getRolesByUser($user->id);

        foreach ($assignedRoles as $role) {
            $roles[] = $role->name;
        }

        return $roles;
    }

    /**
     * Indica si el usuario (por id) puede usar la ruta según RBAC / rutas libres.
     * Útil para filtrar intents de conversación por `required_routes` sin duplicar lógica.
     *
     * @param int|string|null $userId
     */
    public static function userIdCanAccessRoute($userId, string $route): bool
    {
        if ($userId === null || $userId === '') {
            return false;
        }
        $uid = (int) $userId;
        if ($uid <= 0) {
            return false;
        }
        $user = User::findOne($uid);
        if (!$user) {
            return false;
        }
        $routeMap = null;
        if ((int) $user->superadmin !== 1) {
            $routeMap = AllowedRoutesResolver::getTargetRoutesMapForUserId($uid, true);
        }

        return self::userCanAccessRoute($user, $route, $routeMap);
    }

    /**
     * Verificar si el usuario puede acceder a una ruta
     * @param User $user
     * @param string $route
     * @return bool
     */
    /**
     * @param array<string, true>|null $routeMap null = superadmin (todo permitido); [] = solo libres
     */
    private static function userCanAccessRoute($user, $route, $routeMap = null)
    {
        if ((int) $user->superadmin === 1) {
            return true;
        }

        try {
            if (Route::isFreeAccess($route)) {
                return true;
            }

            if ($routeMap === null) {
                $routeMap = AllowedRoutesResolver::getTargetRoutesMapForUserId((int) $user->id, true);
            }

            // Solo superadmin usa mapa null (sin filtro). Si llega null aquí, negar por seguridad.
            if ($routeMap === null) {
                Yii::error(
                    'userCanAccessRoute: mapa de rutas null para usuario no superadmin (id=' . (int) $user->id . ')',
                    'action-mapping'
                );

                return false;
            }

            return AllowedRoutesResolver::routeAllowedByMap($route, $routeMap);
        } catch (\Exception $e) {
            Yii::error("Error verificando acceso a ruta {$route}: " . $e->getMessage(), 'action-mapping');
            return false;
        }
    }

    /**
     * Generar descripción estructurada de acciones para el modelo de IA
     * @param array $actions
     * @return string
     */
    public static function generateActionsDescriptionForIA($actions)
    {
        if (empty($actions)) {
            return "No hay acciones disponibles.";
        }

        $description = "Acciones disponibles en el sistema:\n\n";

        foreach ($actions as $index => $action) {
            $description .= ($index + 1) . ". " . $action['display_name'] . "\n";
            $description .= "   Ruta: " . $action['route'] . "\n";
            $description .= "   Descripción: " . $action['description'] . "\n";

            if (!empty($action['parameters'])) {
                $params = [];
                foreach ($action['parameters'] as $param) {
                    $paramStr = $param['name'];
                    if (!$param['required']) {
                        $paramStr .= " (opcional)";
                    }
                    $params[] = $paramStr;
                }
                $description .= "   Parámetros: " . implode(", ", $params) . "\n";
            }

            $description .= "\n";
        }

        return $description;
    }

    /**
     * Generar formato JSON de acciones para IA
     * @param array $actions
     * @return array
     */
    public static function generateActionsJSONForIA($actions)
    {
        $json = [];

        foreach ($actions as $action) {
            $json[] = [
                'route' => $action['route'],
                'name' => $action['display_name'],
                'description' => $action['description'],
                'controller' => $action['controller'],
                'action' => $action['action'],
                'parameters' => $action['parameters'],
            ];
        }

        return $json;
    }

    /**
     * Invalidar cache de acciones por rol
     * @param int|null $userId
     */
    public static function invalidateCacheForUser($userId = null)
    {
        if (!$userId) {
            return;
        }

        $user = User::findOne($userId);
        if (!$user) {
            return;
        }

        $roles = self::getUserRoles($user);
        $cacheKey = self::CACHE_KEY_PREFIX . md5(implode(',', $roles));

        $cache = Yii::$app->cache;
        if ($cache) {
            $cache->delete($cacheKey);
        }
    }

    /**
     * Invalidar todo el cache de acciones
     */
    public static function invalidateAllCache()
    {
        ActionDiscoveryService::invalidateCache();

        $cache = Yii::$app->cache;
        if ($cache) {
            // Eliminar todas las claves de cache de acciones por rol
            // Nota: Esto es una aproximación, en producción podría necesitar un sistema más sofisticado
            $cache->flush();
        }
    }
}

