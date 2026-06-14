<?php

namespace common\components\Assistant\UiActions;

use common\components\Assistant\Catalog\DataAccessCatalogIntentSupport;
use common\components\Assistant\Catalog\YamlIntentCatalogService;
use common\components\Assistant\Service\AssistantDraftNormalizer;
use common\components\Ui\ApiV1HttpRoute;
use common\components\Ui\UiDefinitionTemplateManager;

/**
 * Enriquece acciones del asistente con {@see $action['client_open']} para que web y apps
 * abran pantallas nativas en lugar de tratar la URL de API como destino de navegación.
 */
final class AssistantClientOpenEnricher
{
    /**
     * @param array<string, mixed> $action acción ya pasada por formatActionsForResponse (action_id, route, parameters, …)
     * @return array<string, mixed>
     */
    public static function enrich(array $action): array
    {
        $route = ApiV1HttpRoute::normalize(AssistantDraftNormalizer::scalarString($action['route'] ?? ''));
        if ($route !== '') {
            $action['route'] = $route;
        }
        $actionId = AssistantDraftNormalizer::scalarString($action['action_id'] ?? '');

        // Si la acción ya trae client_open.kind (ej. nativas descubiertas por catálogo), respetarlo,
        // pero asegurarnos de que la estructura mínima exista.
        if (isset($action['client_open']) && is_array($action['client_open'])) {
            return $action;
        }

        // Flujos conversacionales (YAML o intents DataAccess genéricos).
        if ($actionId !== ''
            && (YamlIntentCatalogService::intentExists($actionId)
                || DataAccessCatalogIntentSupport::isCatalogOnlyIntent($actionId))) {
            $action['client_open'] = [
                'kind' => 'intent',
                'intent_id' => $actionId,
            ];
            $action['client_interaction'] = 'intent_flow';

            return $action;
        }

        // Cierre `flow_submit`: POST sin GET de descriptor (puede existir JSON legacy para errores, no se abre en el asistente).
        if ($route !== '' && self::isPostOnlyFlowClosureRoute($route)) {
            return $action;
        }

        // UI JSON (descriptores): si la ruta apunta a un template existente bajo `views/json/{entidad}/{accion}.json`,
        // el cliente debe abrirla como pantalla dinámica (`ui_json`).
        //
        // Importante: NO inferir por “ser /api/v1/...” porque también hay endpoints de dominio.
        if ($route !== '' && UiDefinitionTemplateManager::hasTemplateForApiRoute($route)) {
            $action['client_open'] = [
                'kind' => 'ui_json',
                'api' => [
                    'route' => $route,
                    'method' => 'GET|POST',
                ],
            ];
            // Nomenclatura sugerida: UI disparada por el asistente (JSON).
            $action['client_interaction'] = 'ui_asistente_json';
            unset($action['spa_presentation']);

            return $action;
        }

        return $action;
    }

    /**
     * Rutas de cierre declarativo de flujos (`flow_submit` / `rbac_route` del YAML): solo POST, sin UI en GET.
     */
    public static function isPostOnlyFlowClosureRoute(string $route): bool
    {
        $route = trim($route);
        if ($route === '') {
            return false;
        }
        $path = parse_url($route, PHP_URL_PATH);
        if (!is_string($path) || $path === '') {
            $path = $route;
        }

        return in_array($path, self::postOnlyFlowClosureRoutes(), true);
    }

    /**
     * @return list<string>
     */
    private static function postOnlyFlowClosureRoutes(): array
    {
        static $routes = null;
        if ($routes === null) {
            $routes = YamlIntentCatalogService::postOnlyFlowClosureRoutes();
        }

        return $routes;
    }
}
