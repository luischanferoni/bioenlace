<?php

namespace common\components\IntentCatalog;

use common\components\Actions\ActionDiscoveryService;
use common\components\Actions\ActionMappingService;
use common\components\Actions\AllowedRoutesResolver;

/**
 * Catálogo de **UIs** sugeribles (intents UI).
 *
 * Definición de UI en este proyecto:
 * - **API UI**: descriptor JSON bajo `/api/v1/ui/<controller>/<action>` (ver UiController).
 * - **HTML UI**: rutas web del frontend (controladores `frontend/controllers`) que renderizan vistas.
 *
 * Este servicio NO incluye endpoints de dominio (turnos/agenda/etc.) porque no son UI.
 */
final class IntentCatalogService
{
    /**
     * UIs del frontend, expuestas como rutas `/api/v1/ui/<controller>/<action>`.
     * Se filtran por RBAC usando action_id permitido del usuario (cuando aplica).
     *
     * @return array<int, array<string, mixed>>
     */
    public static function getAvailableUiForUser(int $userId, bool $useCache = true): array
    {
        if ($userId <= 0) {
            return [];
        }

        // 1) action_ids permitidos (dominio) según RBAC actual.
        $allowedActionIds = [];
        foreach (ActionMappingService::getAvailableActionsForUser($userId, $useCache) as $a) {
            $id = isset($a['action_id']) ? (string) $a['action_id'] : '';
            if ($id !== '') {
                $allowedActionIds[$id] = true;
            }
        }

        // 2) Definiciones UI implementadas en frontend/controllers (taggeables/excluibles).
        $uiDefs = ActionDiscoveryService::discoverFrontendUiDefinitions($useCache);

        // 3) Filtrado final:
        // - Si el usuario tiene el action_id permitido, la UI entra.
        // - Si no lo tiene, igual puede entrar si la ruta es de acceso libre (pantallas públicas).
        $routeMap = null;
        $out = [];
        foreach ($uiDefs as $ui) {
            $actionId = isset($ui['action_id']) ? (string) $ui['action_id'] : '';
            $route = isset($ui['route']) ? (string) $ui['route'] : '';
            if ($route === '' || $route === '/') {
                continue;
            }

            if ($actionId !== '' && isset($allowedActionIds[$actionId])) {
                $out[] = $ui;
                continue;
            }

            // Pantallas públicas: respetar mapa de rutas (free access / subroutes).
            if ($routeMap === null) {
                $routeMap = AllowedRoutesResolver::getTargetRoutesMapForUserId($userId, $useCache);
            }
            if (AllowedRoutesResolver::routeAllowedByMap($route, $routeMap)) {
                $out[] = $ui;
            }
        }

        return $out;
    }
}

