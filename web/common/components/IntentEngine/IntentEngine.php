<?php

namespace common\components\IntentEngine;

use Yii;
use common\components\IntentCatalog\IntentCatalogService;
use common\components\Actions\AssistantClientOpenEnricher;

/**
 * Motor único de intents para el asistente.
 *
 * Fuente de verdad de destinos: UIs JSON bajo `/api/v1/ui/<entity>/<action>` (templates existentes + RBAC).
 */
final class IntentEngine
{
    /**
     * Procesa un mensaje del usuario y sugiere una UI (o lista de UIs) para abrir.
     *
     * @return array<string, mixed>
     */
    public static function processQuery(string $content, int $userId, ?string $actionId = null): array
    {
        $content = trim($content);
        $catalog = UiActionCatalog::forUser($userId);

        if ($catalog->items === []) {
            return [
                'success' => false,
                'error' => 'No hay UIs disponibles para este usuario.',
                'actions' => [],
            ];
        }

        // Ejecución directa por action_id (cuando el cliente ya eligió una UI).
        if ($actionId !== null && $actionId !== '') {
            $item = $catalog->byActionId[$actionId] ?? null;
            if ($item === null) {
                return [
                    'success' => false,
                    'error' => 'action_id no permitido o inexistente para este usuario.',
                    'actions' => [],
                ];
            }
            return self::buildSingleActionResponse($item, 'action_id');
        }

        // Consulta vacía no debería llegar aquí (lo valida el controller), pero toleramos.
        if ($content === '') {
            return [
                'success' => false,
                'error' => 'Se requiere content o action_id.',
                'actions' => [],
            ];
        }

        // “¿Qué puedo hacer?” => listar UIs permitidas (limitado).
        if (self::isListAllQuery($content)) {
            $max = 12;
            $out = [];
            foreach (array_slice($catalog->items, 0, $max) as $it) {
                $out[] = self::formatActionForClient($it);
            }

            return [
                'success' => true,
                'kind' => 'ui_intents_list',
                'explanation' => 'Estas son algunas pantallas disponibles para vos.',
                'actions' => $out,
                'total_actions_available' => count($catalog->items),
            ];
        }

        $classification = IntentClassifier::classify($content, $catalog);
        if ($classification === null) {
            return [
                'success' => true,
                'kind' => 'no_intent_match',
                'explanation' => 'No encontré una pantalla que encaje claramente con tu pedido.',
                'actions' => [],
            ];
        }

        return self::buildSingleActionResponse(
            $classification['item'],
            (string) ($classification['method'] ?? 'unknown'),
            (float) ($classification['confidence'] ?? 0.0)
        );
    }

    private static function buildSingleActionResponse(UiActionCatalogItem $item, string $method, float $confidence = 1.0): array
    {
        return [
            'success' => true,
            'kind' => 'ui_intent_match',
            'explanation' => $item->display_name !== '' ? ('Abrir: ' . $item->display_name) : 'Acción disponible.',
            'actions' => [self::formatActionForClient($item)],
            'match' => [
                'action_id' => $item->action_id,
                'confidence' => max(0.0, min(1.0, $confidence)),
                'method' => $method,
            ],
        ];
    }

    /**
     * Estructura de acción para respuesta del asistente (más `client_open`).
     *
     * @return array<string, mixed>
     */
    private static function formatActionForClient(UiActionCatalogItem $item): array
    {
        $action = [
            'action_id' => $item->action_id,
            'display_name' => $item->display_name,
            'description' => $item->description,
            'entity' => $item->entity,
            'route' => $item->route,
            'parameters' => $item->parameters,
        ];

        return AssistantClientOpenEnricher::enrich($action);
    }

    private static function isListAllQuery(string $q): bool
    {
        $s = mb_strtolower(trim($q), 'UTF-8');
        if ($s === '') {
            return false;
        }

        return preg_match('/\b(que puedo hacer|qué puedo hacer|que opciones tengo|qué opciones tengo|ayuda|menu|menú|opciones|permisos|capacidades)\b/u', $s) === 1;
    }
}

