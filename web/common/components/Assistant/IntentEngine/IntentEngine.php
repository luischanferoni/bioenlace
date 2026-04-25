<?php

namespace common\components\Assistant\IntentEngine;

use Yii;
use common\components\Assistant\Catalog\IntentCatalogService;
use common\components\Assistant\Catalog\YamlIntentCatalogService;
use common\components\Assistant\UiActions\AssistantClientOpenEnricher;
use common\components\Assistant\SubIntentEngine\SubIntentEngine;
use common\components\UiDefinitionTemplateManager;
use common\models\Servicio;
use yii\helpers\Json;

/**
 * Motor único de intents para el asistente.
 *
 * Fuente de verdad de destinos: UIs JSON bajo `/api/v1/<entity>/<action>` (templates existentes + RBAC).
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
            return self::buildSingleActionResponse($item, 'action_id', 1.0, $content, $userId);
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
            (float) ($classification['confidence'] ?? 0.0),
            $content,
            $userId
        );
    }

    private static function buildSingleActionResponse(UiActionCatalogItem $item, string $method, float $confidence = 1.0, string $content = '', int $userId = 0): array
    {
        $action = self::formatActionForClient($item);
        $action = self::enrichProvidedParamsFromQuery($action, $content);

        // Si el descriptor JSON declara `ui_type=flow`, el asistente debe arrancar en modo conversacional (SubIntentEngine),
        // no abriendo el wizard monolítico del descriptor.
        if ($userId > 0 && self::isFlowUiTemplateForCatalogItem($item)) {
            $draft = self::draftFromAssistantActionParameters($action);
            $flow = SubIntentEngine::process(
                [
                    'intent_id' => $item->action_id,
                    'subintent_id' => '',
                    'draft' => $draft,
                    'content' => $content,
                    'interaction' => null,
                ],
                $userId
            );

            if (!empty($flow['success']) && is_array($flow)) {
                // Flow conversacional: el cliente NO debe renderizar botones "abrir UI".
                // Devolvemos `kind=intent_flow` y omitimos `actions`.
                unset($flow['success']);
                $base = [
                    'success' => true,
                    'kind' => 'intent_flow',
                    // `text` viene del SubIntentEngine. `explanation` queda solo para compat/telemetría.
                    'explanation' => $item->display_name !== '' ? ('Iniciar: ' . $item->display_name) : 'Iniciar flujo.',
                    'flow_action_id' => $item->action_id,
                    'match' => [
                        'action_id' => $item->action_id,
                        'confidence' => max(0.0, min(1.0, $confidence)),
                        'method' => $method,
                    ],
                ];

                return array_merge($base, $flow);
            }
        }

        return [
            'success' => true,
            'kind' => 'ui_intent_match',
            'explanation' => $item->display_name !== '' ? ('Abrir: ' . $item->display_name) : 'Acción disponible.',
            'actions' => [$action],
            'match' => [
                'action_id' => $item->action_id,
                'confidence' => max(0.0, min(1.0, $confidence)),
                'method' => $method,
            ],
        ];
    }

    private static function isFlowUiTemplateForCatalogItem(UiActionCatalogItem $item): bool
    {
        // Fuente de verdad: si existe YAML para ese intent_id, es un flow conversacional.
        if (YamlIntentCatalogService::intentExists($item->action_id)) {
            return true;
        }

        $route = trim((string) $item->route);
        if ($route === '') {
            return false;
        }
        $path = parse_url($route, PHP_URL_PATH);
        if (!is_string($path) || $path === '') {
            $path = $route;
        }
        if (preg_match('#^/api/v\d+/([\\w-]+)/([\\w-]+)$#', $path, $m) !== 1) {
            return false;
        }

        $entity = strtolower((string) $m[1]);
        $action = (string) $m[2];
        $file = Yii::getAlias(UiDefinitionTemplateManager::TEMPLATE_BASE_PATH . '/' . $entity . '/' . $action . '.json');
        if (!is_string($file) || $file === '' || !is_file($file)) {
            return false;
        }

        $raw = @file_get_contents($file);
        if (!is_string($raw) || $raw === '') {
            return false;
        }

        try {
            $decoded = Json::decode($raw);
        } catch (\Throwable $e) {
            return false;
        }

        if (!is_array($decoded)) {
            return false;
        }

        return isset($decoded['ui_type']) && is_string($decoded['ui_type']) && strtolower($decoded['ui_type']) === 'flow';
    }

    /**
     * @param array<string, mixed> $action
     * @return array<string, mixed>
     */
    private static function draftFromAssistantActionParameters(array $action): array
    {
        $params = isset($action['parameters']) && is_array($action['parameters']) ? $action['parameters'] : [];
        $provided = isset($params['provided']) && is_array($params['provided']) ? $params['provided'] : [];

        $draft = [];
        foreach ($provided as $k => $v) {
            $key = is_string($k) ? trim($k) : '';
            if ($key === '') {
                continue;
            }
            if (is_array($v) && array_key_exists('value', $v)) {
                $val = $v['value'];
            } else {
                $val = $v;
            }
            if ($val === null) {
                continue;
            }
            $s = trim((string) $val);
            if ($s === '') {
                continue;
            }
            $draft[$key] = $s;
        }

        return $draft;
    }

    /**
     * Completa parámetros "provided" para mejorar UX de UIs wizard.
     * Importante: esto NO reemplaza la UI; solo prellena campos si hay match claro.
     *
     * @param array<string, mixed> $action
     * @return array<string, mixed>
     */
    private static function enrichProvidedParamsFromQuery(array $action, string $content): array
    {
        $actionId = (string) ($action['action_id'] ?? '');
        if ($content === '' || $actionId === '') {
            return $action;
        }

        // Turnos: prefill de servicio para wizard de autogestión.
        if ($actionId === 'turnos.crear-como-paciente' || $actionId === 'turnos.crear-para-paciente') {
            $idServicio = Servicio::extractFromQuery($content);
            if ($idServicio) {
                if (!isset($action['parameters']) || !is_array($action['parameters'])) {
                    $action['parameters'] = ['expected' => [], 'provided' => []];
                }
                if (!isset($action['parameters']['provided']) || !is_array($action['parameters']['provided'])) {
                    $action['parameters']['provided'] = [];
                }
                // UI JSON usa id_servicio_asignado como nombre de campo.
                $action['parameters']['provided']['id_servicio_asignado'] = [
                    'value' => (string) (int) $idServicio,
                    'source' => 'extracted',
                ];
            }
        }

        return $action;
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
        if ($item->spa_presentation !== null && $item->spa_presentation !== '') {
            $action['spa_presentation'] = $item->spa_presentation;
        }

        if ($item->client_open !== null) {
            $action['client_open'] = $item->client_open;
        }
        if ($item->client_interaction !== null && $item->client_interaction !== '') {
            $action['client_interaction'] = $item->client_interaction;
        }

        // Para UIs JSON (descriptor en `views/json/...`) el enrich agrega client_open si no vino explícito.
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

