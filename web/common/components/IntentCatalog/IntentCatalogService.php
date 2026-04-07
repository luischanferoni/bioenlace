<?php

namespace common\components\IntentCatalog;

use Yii;
use common\components\Actions\ActionMappingService;
use common\components\UiDefinitionTemplateManager;

/**
 * Catálogo de **UIs** sugeribles (intents UI).
 *
 * Definición de UI en este proyecto:
 * - **API UI**: descriptor JSON bajo `/api/v1/ui/<entity>/<action>` cargado desde templates JSON (ver UiController).
 *
 * Este servicio NO incluye endpoints de dominio (turnos/agenda/etc.) porque no son UI.
 */
final class IntentCatalogService
{
    /**
     * UIs disponibles para un usuario.
     *
     * Fuente: acciones permitidas por RBAC (ActionMappingService) mapeadas a rutas de descriptor UI:
     * `/api/v1/ui/<controller>/<action>`.
     *
     * Nota: esto NO enumera endpoints de dominio; los convierte a “destinos UI” (descriptor) bajo `/ui/`.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function getAvailableUiForUser(int $userId, bool $useCache = true): array
    {
        if ($userId <= 0) {
            return [];
        }

        $actions = ActionMappingService::getAvailableActionsForUser($userId, $useCache);
        $byId = [];
        foreach ($actions as $a) {
            $id = isset($a['action_id']) ? (string) $a['action_id'] : '';
            if ($id !== '') {
                $byId[$id] = $a;
            }
        }

        // Enumerar templates JSON existentes (excepto common/*).
        $base = Yii::getAlias(UiDefinitionTemplateManager::TEMPLATE_BASE_PATH);
        $files = glob($base . DIRECTORY_SEPARATOR . '*' . DIRECTORY_SEPARATOR . '*.json') ?: [];

        $out = [];
        foreach ($files as $path) {
            $entity = basename(dirname($path));
            if ($entity === 'common') {
                continue;
            }
            $action = basename($path, '.json');
            $actionId = strtolower($entity . '.' . $action);

            // Sólo listar si el usuario tiene la acción permitida por RBAC.
            if (!isset($byId[$actionId])) {
                continue;
            }

            $a = $byId[$actionId];
            $a['controller'] = $entity;
            $a['action'] = $action;
            $a['route'] = '/api/v1/ui/' . rawurlencode($entity) . '/' . rawurlencode($action);
            $out[] = $a;
        }

        return $out;
    }
}

