<?php

namespace common\components\Platform\Assistant\Catalog;

use common\components\Platform\Core\Service\ClientContextService;
use Yii;

/**
 * Catálogo de **UIs** sugeribles (intents UI).
 *
 * Definición de UI en este proyecto:
 * - **API UI JSON**: descriptor JSON bajo `/api/v1/<entity>/<action>` cargado desde templates JSON.
 *
 * Este servicio NO incluye endpoints de negocio porque no son UI.
 */
final class IntentCatalogService
{
    /**
     * UIs disponibles para un usuario.
     *
     * Fuente: acciones permitidas por RBAC (ActionMappingService) mapeadas a rutas de descriptor UI:
     * `/api/v1/<controller>/<action>`.
     *
     * Nota: esto NO enumera endpoints de dominio; los convierte a “destinos UI” (descriptor) bajo `/api/v1/<entidad>/<accion>`.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function getAvailableUiForUser(int $userId, bool $useCache = true): array
    {
        // Intents YAML filtrados por permiso intent_id ({@see IntentAccessService}).
        $all = YamlIntentCatalogService::discoverAll($useCache);

        $filtered = YamlIntentCatalogService::filterByRbac($all, $userId);

        return ClientContextService::filterPacienteFlows($filtered);
    }
}

