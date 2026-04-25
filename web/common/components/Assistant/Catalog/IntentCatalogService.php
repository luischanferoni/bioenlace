<?php

namespace common\components\Assistant\Catalog;

use Yii;

/**
 * Catálogo de **UIs** sugeribles (intents UI).
 *
 * Definición de UI en este proyecto:
 * - **API UI JSON**: descriptor JSON bajo `/api/v1/<entity>/<action>` cargado desde templates JSON.
 *
 * Este servicio NO incluye endpoints de dominio (turnos/agenda/etc.) porque no son UI.
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
        // Nuevo: el asistente sugiere intents conversacionales desde YAML (fuente de verdad).
        // No depende de enumerar templates JSON en views/json.
        return YamlIntentCatalogService::discoverAll($useCache);
    }
}

