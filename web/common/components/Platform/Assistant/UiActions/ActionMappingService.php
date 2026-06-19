<?php

namespace common\components\Platform\Assistant\UiActions;

use common\components\Platform\Assistant\Catalog\IntentCatalogActionMapper;
use common\components\Platform\Assistant\Catalog\IntentCatalogService;
use common\components\Platform\Assistant\Catalog\UiActionCatalogProviderRegistry;
use common\components\Platform\Assistant\Service\AssistantDraftNormalizer;
use common\components\Platform\Core\Permission\BioenlaceRbacRevision;
use common\components\Platform\Core\Permission\RbacFreeRouteChecker;
use common\components\Platform\Core\Service\ClientContextService;
use common\models\User;
use Yii;

/**
 * Acciones consumibles por IA / descubrimiento: solo intents autorizados ({@see IntentCatalogService}).
 */
class ActionMappingService
{
    public const CACHE_KEY_PREFIX = 'actions_for_role_v8_';
    public const CACHE_DURATION = 1800;

    /**
     * Intents permitidos al usuario, en formato de acción descubierta (compat legacy).
     *
     * @return list<array<string, mixed>>
     */
    public static function getAvailableActionsForUser($userId = null, $useCache = true): array
    {
        if (!$userId) {
            return [];
        }

        $useCache = ActionCatalogSettings::shouldUseCache($useCache);
        $userId = (int) $userId;
        if ($userId <= 0 || User::findOne($userId) === null) {
            return [];
        }

        $cacheKey = self::cacheKeyForUser($userId);
        $cache = Yii::$app->cache;
        if ($useCache && $cache) {
            $cached = $cache->get($cacheKey);
            if ($cached !== false && is_array($cached)) {
                return $cached;
            }
        }

        $availableActions = self::buildPermittedIntentActions($userId);
        $availableActions = self::dedupeActionsByRoute($availableActions);

        Yii::info(
            'ActionMappingService: acciones intent permitidas=' . count($availableActions) . ' userId=' . $userId,
            'action-mapping'
        );

        if ($useCache && $cache) {
            $cache->set($cacheKey, $availableActions, self::CACHE_DURATION);
        }

        return $availableActions;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function buildPermittedIntentActions(int $userId): array
    {
        $byIntentId = [];
        foreach (IntentCatalogService::getAvailableUiForUser($userId, false) as $flow) {
            if (!is_array($flow)) {
                continue;
            }
            $intentId = AssistantDraftNormalizer::scalarString($flow['action_id'] ?? '');
            if ($intentId === '') {
                continue;
            }
            $byIntentId[$intentId] = IntentCatalogActionMapper::toDiscoveredAction($flow);
        }

        foreach (UiActionCatalogProviderRegistry::forUserFromProviders($userId) as $flow) {
            if (!is_array($flow)) {
                continue;
            }
            $intentId = AssistantDraftNormalizer::scalarString($flow['action_id'] ?? '');
            if ($intentId === '' || isset($byIntentId[$intentId])) {
                continue;
            }
            $byIntentId[$intentId] = IntentCatalogActionMapper::toDiscoveredAction($flow);
        }

        return array_values($byIntentId);
    }

    private static function cacheKeyForUser(int $userId): string
    {
        return self::CACHE_KEY_PREFIX
            . $userId
            . '_r' . BioenlaceRbacRevision::current()
            . ClientContextService::rbacCacheSuffix();
    }

    /**
     * @param list<array<string, mixed>> $actions
     * @return list<array<string, mixed>>
     */
    private static function dedupeActionsByRoute(array $actions): array
    {
        $seen = [];
        $out = [];
        foreach ($actions as $action) {
            $intentId = trim((string) ($action['action_id'] ?? ''));
            if ($intentId !== '') {
                if (isset($seen['id:' . $intentId])) {
                    continue;
                }
                $seen['id:' . $intentId] = true;
                $out[] = $action;
                continue;
            }

            $route = isset($action['route']) ? '/' . ltrim((string) $action['route'], '/') : '';
            if ($route === '' || $route === '/') {
                $out[] = $action;
                continue;
            }
            if (isset($seen[$route])) {
                continue;
            }
            $seen[$route] = true;
            $out[] = $action;
        }

        return $out;
    }

    /**
     * Chequeo de ruta HTTP (API / permisos legacy de ruta). No sustituye {@see IntentAccessService}.
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
     * @param array<string, true>|null $routeMap null = superadmin (todo permitido); [] = solo libres
     */
    private static function userCanAccessRoute($user, $route, $routeMap = null): bool
    {
        if ((int) $user->superadmin === 1) {
            return true;
        }

        try {
            $rbacPath = AllowedRoutesResolver::apiHttpPathToPermissionRoute($route);
            if (RbacFreeRouteChecker::isFreeAccess($route) || ($rbacPath !== $route && RbacFreeRouteChecker::isFreeAccess($rbacPath))) {
                return true;
            }

            if ($routeMap === null) {
                $routeMap = AllowedRoutesResolver::getTargetRoutesMapForUserId((int) $user->id, true);
            }

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
     * @param list<array<string, mixed>> $actions
     */
    public static function generateActionsDescriptionForIA($actions): string
    {
        if ($actions === []) {
            return 'No hay acciones disponibles.';
        }

        $description = "Acciones disponibles en el sistema:\n\n";
        foreach ($actions as $index => $action) {
            $description .= ($index + 1) . '. ' . ($action['display_name'] ?? '') . "\n";
            $description .= '   Ruta: ' . ($action['route'] ?? '') . "\n";
            $description .= '   Descripción: ' . ($action['description'] ?? '') . "\n";
            if (!empty($action['parameters']) && is_array($action['parameters'])) {
                $params = [];
                foreach ($action['parameters'] as $param) {
                    if (!is_array($param)) {
                        continue;
                    }
                    $paramStr = (string) ($param['name'] ?? '');
                    if ($paramStr === '') {
                        continue;
                    }
                    if (empty($param['required'])) {
                        $paramStr .= ' (opcional)';
                    }
                    $params[] = $paramStr;
                }
                if ($params !== []) {
                    $description .= '   Parámetros: ' . implode(', ', $params) . "\n";
                }
            }
            $description .= "\n";
        }

        return $description;
    }

    /**
     * @param list<array<string, mixed>> $actions
     * @return list<array<string, mixed>>
     */
    public static function generateActionsJSONForIA($actions): array
    {
        $json = [];
        foreach ($actions as $action) {
            $json[] = [
                'route' => $action['route'] ?? '',
                'name' => $action['display_name'] ?? '',
                'description' => $action['description'] ?? '',
                'controller' => $action['controller'] ?? '',
                'action' => $action['action'] ?? '',
                'action_id' => $action['action_id'] ?? '',
                'parameters' => $action['parameters'] ?? [],
            ];
        }

        return $json;
    }

    public static function invalidateCacheForUser($userId = null): void
    {
        if (!$userId) {
            return;
        }
        $userId = (int) $userId;
        if ($userId <= 0) {
            return;
        }

        $cache = Yii::$app->cache;
        if ($cache) {
            $cache->delete(self::cacheKeyForUser($userId));
        }
    }

    public static function invalidateAllCache(): void
    {
        ActionDiscoveryService::invalidateCache();
        BioenlaceRbacRevision::bump();
    }
}
