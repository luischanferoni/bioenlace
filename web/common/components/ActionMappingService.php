<?php

namespace common\components;

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
    const CACHE_KEY_PREFIX = 'actions_for_role_';
    const CACHE_DURATION = 1800; // 30 minutos

    /**
     * Obtener acciones disponibles para el usuario logueado
     * Filtra por roles y permisos del usuario
     * @param int|null $userId ID del usuario (si es null, usa el usuario actual de la sesión)
     * @param bool $useCache Usar cache
     * @return array
     */
    public static function getAvailableActionsForUser($userId = null, $useCache = true)
    {
        // Si no se proporciona userId, intentar obtenerlo de la sesión
        if ($userId === null) {
            $userId = Yii::$app->user->id;
        }

        if (!$userId) {
            Yii::warning("ActionMappingService::getAvailableActionsForUser - Usuario no autenticado", 'action-mapping');
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
        
        // Filtrar acciones por permisos del usuario
        $availableActions = [];
        
        foreach ($allActions as $action) {
            // Verificar si el usuario tiene acceso a esta ruta
            if (self::userCanAccessRoute($user, $action['route'])) {
                $availableActions[] = $action;
            }
        }
        
        // Log para debugging
        Yii::info("ActionMappingService: Acciones disponibles después de filtrar: " . count($availableActions) . " para usuario: {$userId}", 'action-mapping');

        // Guardar en cache
        if ($cache) {
            $cache->set($cacheKey, $availableActions, self::CACHE_DURATION);
        }

        return $availableActions;
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
     * Verificar si el usuario puede acceder a una ruta
     * @param User $user
     * @param string $route
     * @return bool
     */
    private static function userCanAccessRoute($user, $route)
    {
        // Superadmin tiene acceso a todo
        if ($user->superadmin == 1) {
            return true;
        }

        try {
            // Verificar si la ruta es de acceso libre
            if (Route::isFreeAccess($route)) {
                return true;
            }

            // Obtener rutas permitidas para el usuario desde RBAC
            $authManager = Yii::$app->authManager;
            $permissions = $authManager->getPermissionsByUser($user->id);
            
            // Verificar si alguna permiso coincide con la ruta
            foreach ($permissions as $permission) {
                if ($permission->name === $route) {
                    return true;
                }
                // Verificar rutas con sub-rutas (ej: /site/* incluye /site/index)
                if (strpos($route, $permission->name) === 0) {
                    return true;
                }
            }

            // Verificar roles del usuario y sus permisos
            $roles = $authManager->getRolesByUser($user->id);
            foreach ($roles as $role) {
                $rolePermissions = $authManager->getPermissionsByRole($role->name);
                foreach ($rolePermissions as $permission) {
                    if ($permission->name === $route) {
                        return true;
                    }
                    if (strpos($route, $permission->name) === 0) {
                        return true;
                    }
                }
            }

            // Si el usuario actual es el mismo, usar el método canRoute
            if (Yii::$app->user->id == $user->id) {
                return User::canRoute($route, false);
            }

            return false;
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
        if ($userId === null) {
            $userId = Yii::$app->user->id;
        }

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

