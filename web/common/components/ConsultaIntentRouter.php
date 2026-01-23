<?php

namespace common\components;

use Yii;
use common\components\IntentClassifier;
use common\components\ConversationContext;
use common\components\ParameterExtractor;
use common\components\intent_handlers\BaseIntentHandler;

/**
 * Orquestador principal de consultas de pacientes
 * 
 * Coordina el flujo completo:
 * 1. Carga contexto de conversación
 * 2. Clasifica intent del mensaje
 * 3. Extrae parámetros
 * 4. Enruta a handler específico
 * 5. Genera respuesta estructurada
 */
class ConsultaIntentRouter
{
    /**
     * Procesar consulta del usuario
     * @param string $message Mensaje del usuario
     * @param int|string|null $userId ID del usuario
     * @param string $botId ID del bot (default: 'BOT')
     * @return array Respuesta estructurada
     */
    public static function process($message, $userId = null, $botId = 'BOT')
    {
        try {
            // 1. Cargar contexto de conversación
            $context = ConversationContext::load($userId, $botId);
            
            // 2. Clasificar intent
            $classification = IntentClassifier::classify($message, $context);
            
            $category = $classification['category'];
            $intent = $classification['intent'];
            $confidence = $classification['confidence'];
            $method = $classification['method'];
            
            Yii::info("ConsultaIntentRouter: Clasificado como {$category}->{$intent} (confidence: {$confidence}, method: {$method})", 'consulta-intent-router');
            
            // 3. Extraer parámetros del mensaje
            $parameters = ParameterExtractor::extract($message, $intent, $context);
            
            // 4. Fusionar contexto con nuevos parámetros
            $context = ConversationContext::merge($context, $intent, $parameters);
            
            // 5. Enrutar a handler específico
            $handler = self::getHandler($category, $intent);
            
            if (!$handler) {
                Yii::warning("ConsultaIntentRouter: No se encontró handler para {$category}->{$intent}", 'consulta-intent-router');
                return self::generateFallbackResponse($message);
            }
            
            // 6. Procesar con handler
            $response = $handler->handle($intent, $message, $parameters, $context, $userId);
            
            // 7. Actualizar contexto si es necesario
            if (isset($response['context_update'])) {
                $context = $response['context_update'];
                unset($response['context_update']);
            }
            
            // Guardar contexto actualizado
            ConversationContext::save($userId, $context, $botId);
            
            // 8. Agregar metadatos a la respuesta
            $response['metadata'] = [
                'category' => $category,
                'intent' => $intent,
                'confidence' => $confidence,
                'detection_method' => $method,
                'parameters_extracted' => $parameters
            ];
            
            return $response;
            
        } catch (\Exception $e) {
            Yii::error("ConsultaIntentRouter: Error procesando consulta: " . $e->getMessage() . "\n" . $e->getTraceAsString(), 'consulta-intent-router');
            
            return [
                'success' => false,
                'error' => 'Error al procesar la consulta. Por favor, intente nuevamente.',
                'metadata' => [
                    'error_details' => $e->getMessage()
                ]
            ];
        }
    }
    
    /**
     * Obtener handler para categoría e intent
     * @param string $category
     * @param string $intent
     * @return BaseIntentHandler|null
     */
    private static function getHandler($category, $intent)
    {
        $categories = require Yii::getAlias('@common/config/chatbot/intent-categories.php');
        
        if (!isset($categories[$category]['intents'][$intent])) {
            return null;
        }
        
        $intentConfig = $categories[$category]['intents'][$intent];
        $handlerName = $intentConfig['handler'] ?? null;
        
        if (!$handlerName) {
            return null;
        }
        
        // Construir nombre completo de la clase
        $handlerClass = "common\\components\\intent_handlers\\{$handlerName}";
        
        if (!class_exists($handlerClass)) {
            Yii::warning("ConsultaIntentRouter: Handler class '{$handlerClass}' no existe", 'consulta-intent-router');
            return null;
        }
        
        return new $handlerClass();
    }
    
    /**
     * Generar respuesta de fallback
     * @param string $message
     * @return array
     */
    private static function generateFallbackResponse($message)
    {
        return [
            'success' => true,
            'needs_more_info' => false,
            'response' => [
                'text' => 'No pude entender tu consulta. ¿Podrías reformularla? Puedo ayudarte con turnos, información de salud, farmacias, y más.'
            ],
            'suggestions' => [
                'Sacar un turno',
                'Ver mi historia clínica',
                'Farmacias de turno',
                'Información de salud'
            ],
            'metadata' => [
                'category' => 'general',
                'intent' => 'fuera_de_alcance',
                'confidence' => 0.3,
                'detection_method' => 'fallback'
            ]
        ];
    }
    
    /**
     * Obtener información de un intent
     * @param string $category
     * @param string $intent
     * @return array|null
     */
    public static function getIntentInfo($category, $intent)
    {
        return IntentClassifier::getIntentInfo($category, $intent);
    }
}
