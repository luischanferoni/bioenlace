<?php

namespace common\components\Assistant\IntentEngine;

use Yii;
use common\components\Assistant\Catalog\IntentCatalogService;
use common\components\Assistant\Catalog\DataAccessCatalogIntentSupport;
use common\components\Assistant\Catalog\YamlIntentCatalogService;
use common\components\Assistant\Service\AssistantDraftNormalizer;
use common\components\Assistant\UiActions\AssistantClientOpenEnricher;
use common\components\Assistant\SubIntentEngine\FlowDraftHydratorService;
use common\components\Assistant\SubIntentEngine\IntentBusinessRules;
use common\components\Assistant\SubIntentEngine\SubIntentEngine;
use common\components\Assistant\EntryPoints\Chat\ChatPreprocessContext;
use common\components\Assistant\Service\FlowHintService;
use common\components\Ui\UiDefinitionTemplateManager;
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
                'text' => 'Estas son algunas pantallas disponibles para vos.',
                'actions' => $out,
                'total_actions_available' => count($catalog->items),
            ];
        }

        $classification = IntentClassifier::classify($content, $catalog);
        if ($classification === null) {
            return self::processQueryNoMatch($content, $catalog);
        }

        // Si la IA pide desambiguar, devolver intent_remediation antes de arrancar un flow.
        if (isset($classification['disambiguation']) && is_array($classification['disambiguation'])) {
            $d = $classification['disambiguation'];
            $text = isset($d['text']) ? trim((string) $d['text']) : '';
            $rem = isset($d['remediation']) && is_array($d['remediation']) ? $d['remediation'] : [];
            if ($text !== '' && $rem !== []) {
                $match = [
                    'action_id' => $classification['item']->action_id,
                    'confidence' => (float) ($classification['confidence'] ?? 0.0),
                    'method' => (string) ($classification['method'] ?? 'unknown'),
                ];
                if (isset($classification['ai']) && is_array($classification['ai'])) {
                    $match['ai'] = $classification['ai'];
                }
                return [
                    'success' => true,
                    'text' => $text,
                    'candidate_intent_id' => $classification['item']->action_id,
                    'rule_id' => 'ai_disambiguation',
                    'remediation' => $rem,
                    'match' => $match,
                ];
            }
        }

        $method = (string) ($classification['method'] ?? 'unknown');
        $confidence = (float) ($classification['confidence'] ?? 0.0);
        $out = self::buildSingleActionResponse(
            $classification['item'],
            $method,
            $confidence,
            $content,
            $userId
        );
        // Propagar explicación IA (why/assumptions) al match para debug/telemetría.
        if (isset($classification['ai']) && is_array($classification['ai']) && isset($out['match']) && is_array($out['match'])) {
            $out['match']['ai'] = $classification['ai'];
        }
        return $out;
    }

    public static function isListAllQueryPublic(string $q): bool
    {
        return self::isListAllQuery($q);
    }

    /**
     * @return array<string, mixed>
     */
    public static function processQueryNoMatch(string $content, UiActionCatalog $catalog): array
    {
        $actionIds = [];
        foreach (array_slice($catalog->items, 0, 20) as $it) {
            $actionIds[] = $it->action_id;
        }
        Yii::warning(
            'IntentEngine: no_intent_match. items=' . count($catalog->items)
            . ' first_action_ids=' . Json::encode($actionIds)
            . ' content=' . Json::encode(mb_substr($content, 0, 220, 'UTF-8')),
            'asistente'
        );

        $suggest = [];
        foreach (IntentClassifier::suggestByRules($content, $catalog, 8) as $it) {
            $suggest[] = self::formatActionForClient($it);
        }

        return [
            'success' => true,
            'text' => 'No encontré una pantalla que encaje claramente con tu pedido.',
            'actions' => $suggest,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function buildSingleActionResponsePublic(
        UiActionCatalogItem $item,
        string $method,
        float $confidence = 1.0,
        string $content = '',
        int $userId = 0
    ): array {
        return self::buildSingleActionResponse($item, $method, $confidence, $content, $userId);
    }

    private static function buildSingleActionResponse(UiActionCatalogItem $item, string $method, float $confidence = 1.0, string $content = '', int $userId = 0): array
    {
        $action = self::formatActionForClient($item);
        $action = self::enrichProvidedParamsFromQuery($action, $content);

        // Si el action_id corresponde a un intent YAML, el asistente debe arrancar en modo conversacional (SubIntentEngine),
        // no abriendo un wizard monolítico.
        if ($userId > 0 && self::isFlowUiTemplateForCatalogItem($item)) {
            $draft = self::draftFromAssistantActionParameters($action);
            $blocked = IntentBusinessRules::evaluatePreFlow($item->action_id, $content, $draft, $userId);
            if ($blocked !== null) {
                return [
                    'success' => true,
                    'text' => $blocked['text'],
                    'candidate_intent_id' => $item->action_id,
                    'rule_id' => $blocked['rule_id'],
                    'remediation' => $blocked['remediation'],
                    'match' => [
                        'action_id' => $item->action_id,
                        'confidence' => max(0.0, min(1.0, $confidence)),
                        'method' => $method,
                    ],
                ];
            }
            $hints = FlowHintService::resolveForIntent(
                $item->action_id,
                ChatPreprocessContext::extractions(),
                $userId,
                $draft
            );

            $flowBody = [
                'intent_id' => $item->action_id,
                'subintent_id' => '',
                'draft' => $draft,
                'content' => $content,
                'interaction' => null,
                'hints' => $hints,
            ];
            try {
                FlowDraftHydratorService::hydrateFromIntentManifest($item->action_id, $flowBody);
            } catch (\yii\web\ForbiddenHttpException $e) {
                return [
                    'success' => false,
                    'error' => $e->getMessage(),
                ];
            } catch (\InvalidArgumentException $e) {
                return [
                    'success' => false,
                    'error' => $e->getMessage(),
                ];
            } catch (\RuntimeException $e) {
                return [
                    'success' => false,
                    'error' => $e->getMessage(),
                ];
            }

            $flow = SubIntentEngine::process($flowBody, $userId);

            if (!empty($flow['success']) && is_array($flow)) {
                unset($flow['success']);
                $intro = $item->display_name !== '' ? ('Iniciar: ' . $item->display_name) : 'Iniciar flujo.';
                if (!isset($flow['text']) || trim((string) $flow['text']) === '') {
                    $flow['text'] = $intro;
                }
                $base = [
                    'success' => true,
                    'flow_action_id' => $item->action_id,
                    'match' => [
                        'action_id' => $item->action_id,
                        'confidence' => max(0.0, min(1.0, $confidence)),
                        'method' => $method,
                    ],
                ];
                if ($hints !== []) {
                    $base['hints'] = $hints;
                }

                return array_merge($base, $flow);
            }
        }

        return [
            'success' => true,
            'text' => $item->display_name !== '' ? ('Abrir: ' . $item->display_name) : 'Acción disponible.',
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
        if (DataAccessCatalogIntentSupport::isCatalogOnlyIntent($item->action_id)) {
            return true;
        }

        return YamlIntentCatalogService::intentExists($item->action_id);
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
            $s = AssistantDraftNormalizer::asOptionalString($val);
            if ($s === null) {
                continue;
            }
            $draft[$key] = $s;
        }

        return AssistantDraftNormalizer::normalize($draft);
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

