<?php

namespace common\components\Assistant\UiActions;

use common\components\Assistant\Catalog\YamlIntentCatalogService;
use common\components\UiDefinitionTemplateManager;

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
        $route = (string) ($action['route'] ?? '');
        $actionId = trim((string) ($action['action_id'] ?? ''));

        // Si la acción ya trae client_open.kind (ej. nativas descubiertas por catálogo), respetarlo,
        // pero asegurarnos de que la estructura mínima exista.
        if (isset($action['client_open']) && is_array($action['client_open'])) {
            return $action;
        }

        // Flujos conversacionales (YAML en SubIntentEngine): el cliente arranca con POST action_id al asistente.
        if ($actionId !== '' && YamlIntentCatalogService::intentExists($actionId)) {
            $action['client_open'] = [
                'kind' => 'intent',
                'intent_id' => $actionId,
            ];
            $action['client_interaction'] = 'intent_flow';

            return $action;
        }

        // Mutación solo por POST (p. ej. cierre `flow_submit`): puede existir plantilla para errores de submit,
        // pero el asistente no debe abrir GET de descriptor en el paso final.
        if (self::isAssistantPostOnlyMutationRoute($route)) {
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

    private static function isAssistantPostOnlyMutationRoute(string $route): bool
    {
        $route = trim($route);
        if ($route === '') {
            return false;
        }
        $path = parse_url($route, PHP_URL_PATH);
        if (!is_string($path) || $path === '') {
            $path = $route;
        }

        return in_array($path, self::POST_ONLY_MUTATION_ROUTES, true);
    }

    /** Rutas con plantilla JSON para errores de submit, pero sin GET en el cierre del asistente (`flow_submit`). */
    private const POST_ONLY_MUTATION_ROUTES = [
        '/api/v1/turnos/cancelar-como-paciente',
        '/api/v1/turnos/reprogramar-como-paciente',
    ];
}
