<?php

namespace common\components\Chatbot;

use Yii;
use common\components\MensajeIntent\MensajeCatalogBuilder;
use common\components\MensajeIntent\MensajeCatalogClassifier;
use common\components\Chatbot\ConversationContext;
use common\components\Chatbot\ParameterExtractor;
use common\components\Chatbot\IntentHandlers\IntentHandlerRegistry;
use common\components\Chatbot\Classification\IntentClassifier;

/**
 * Orquestador del mensaje del usuario → intent / acción permitida.
 *
 * Mismo pipeline para chat y para flujos tipo “acciones”: catálogo acotado por RBAC,
 * reglas y luego IA solo sobre ese listado.
 */
class MensajeIntentRouter
{
    /**
     * @param string $message
     * @param int|string|null $userId
     * @param string $botId
     * @return array
     */
    public static function process($message, $userId = null, $botId = 'BOT')
    {
        try {
            $context = ConversationContext::load($userId, $botId);

            $catalog = MensajeCatalogBuilder::buildForMessaging($userId, true);
            $match = MensajeCatalogClassifier::classify($message, $context, $catalog);

            if ($match === null) {
                Yii::info('MensajeIntentRouter: sin clasificación de catálogo, fallback', 'mensaje-intent-router');

                return self::generateFallbackResponse($message);
            }

            $item = $match['item'];
            $confidence = $match['confidence'];
            $method = $match['method'];
            $category = $match['category'] ?? null;
            $intent = $match['intent'] ?? null;

            Yii::info(
                "MensajeIntentRouter: item={$item->action_id} (confidence: {$confidence}, method: {$method})",
                'mensaje-intent-router'
            );

            if ($item->isConversation()) {
                if ($category === null || $intent === null) {
                    return self::generateFallbackResponse($message);
                }

                $parameters = ParameterExtractor::extract($message, $intent, $context);
                $context = ConversationContext::merge($context, $intent, $parameters);

                $handler = self::getHandler($category, $intent);
                if (!$handler) {
                    Yii::warning("MensajeIntentRouter: sin handler para {$category}->{$intent}", 'mensaje-intent-router');

                    return self::generateFallbackResponse($message);
                }

                $response = $handler->handle($intent, $message, $parameters, $context, $userId);

                if (isset($response['context_update'])) {
                    $context = $response['context_update'];
                    unset($response['context_update']);
                }

                ConversationContext::save($userId, $context, $botId);

                $response['metadata'] = array_merge($response['metadata'] ?? [], [
                    'category' => $category,
                    'intent' => $intent,
                    'confidence' => $confidence,
                    'detection_method' => $method,
                    'parameters_extracted' => $parameters,
                    'matched_action_id' => $item->action_id,
                    'matched_route' => $item->route,
                ]);

                return $response;
            }

            // Acción descubierta (ruta RBAC): sin ParameterExtractor de intents de chat
            ConversationContext::save($userId, $context, $botId);

            return [
                'success' => true,
                'needs_more_info' => false,
                'response' => [
                    'text' => 'Detecté la acción «' . $item->title . '». Podés abrirla desde el menú o el asistente de acciones.',
                    'data' => [
                        'route' => $item->route,
                        'action_id' => $item->action_id,
                    ],
                ],
                'actions' => [],
                'suggestions' => [],
                'metadata' => [
                    'category' => null,
                    'intent' => null,
                    'confidence' => $confidence,
                    'detection_method' => $method,
                    'parameters_extracted' => [],
                    'matched_action_id' => $item->action_id,
                    'matched_route' => $item->route,
                    'resolution' => 'discovered_action',
                ],
            ];
        } catch (\Exception $e) {
            Yii::error('MensajeIntentRouter: ' . $e->getMessage() . "\n" . $e->getTraceAsString(), 'mensaje-intent-router');

            return [
                'success' => false,
                'error' => 'Error al procesar la consulta. Por favor, intente nuevamente.',
                'metadata' => [
                    'error_details' => $e->getMessage(),
                ],
            ];
        }
    }

    /**
     * @param string $category
     * @param string $intent
     * @return object|null
     */
    private static function getHandler($category, $intent)
    {
        return IntentHandlerRegistry::getHandler($category, $intent);
    }

    private static function generateFallbackResponse($message)
    {
        return [
            'success' => true,
            'needs_more_info' => false,
            'response' => [
                'text' => 'No pude entender tu consulta. ¿Podrías reformularla? Puedo ayudarte con turnos, información de salud, farmacias, y más.',
            ],
            'suggestions' => [
                'Sacar un turno',
                'Ver mi historia clínica',
                'Farmacias de turno',
                'Información de salud',
            ],
            'metadata' => [
                'category' => 'general',
                'intent' => 'fuera_de_alcance',
                'confidence' => 0.3,
                'detection_method' => 'fallback',
            ],
        ];
    }

    public static function getIntentInfo($category, $intent)
    {
        return IntentClassifier::getIntentInfo($category, $intent);
    }
}
