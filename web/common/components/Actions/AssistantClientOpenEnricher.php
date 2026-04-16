<?php

namespace common\components\Actions;

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

        // Si la acción ya trae client_open.kind (ej. nativas descubiertas por catálogo), respetarlo,
        // pero asegurarnos de que la estructura mínima exista.
        if (isset($action['client_open']) && is_array($action['client_open'])) {
            $co = &$action['client_open'];
            if (!isset($co['presentation']) || !is_string($co['presentation']) || $co['presentation'] === '') {
                $kind = isset($co['kind']) ? (string) $co['kind'] : '';
                $co['presentation'] = ($kind === 'native') ? 'inline' : 'fullscreen';
            }
            return $action;
        }

        // UI JSON (descriptores): si la ruta apunta a un template existente bajo `views/json/{entidad}/{accion}.json`,
        // el cliente debe abrirla como pantalla dinámica (`ui_json`).
        //
        // Importante: NO inferir por “ser /api/v1/...” porque también hay endpoints de dominio.
        if ($route !== '' && UiDefinitionTemplateManager::hasTemplateForApiRoute($route)) {
            $presentation = 'fullscreen';
            if (isset($action['spa_presentation'])) {
                $sp = strtolower(trim((string) $action['spa_presentation']));
                if ($sp === 'inline' || $sp === 'fullscreen') {
                    $presentation = $sp;
                }
            }
            $action['client_open'] = [
                'kind' => 'ui_json',
                'presentation' => $presentation,
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
}
