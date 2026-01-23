<?php

namespace common\components;

use Yii;
use common\models\Dialogo;

/**
 * Gestión de contexto estructurado de conversación
 * 
 * Almacena solo parámetros relevantes por intent, no todo el historial de mensajes.
 * Minimiza el prompt enviado a la IA.
 */
class ConversationContext
{
    /**
     * Cargar contexto desde el diálogo
     * @param int|string $userId ID del usuario
     * @param string $botId ID del bot (default: 'BOT')
     * @return array Contexto estructurado
     */
    public static function load($userId, $botId = 'BOT')
    {
        $dialogo = Dialogo::findOne(['usuario_id' => $userId, 'bot_id' => $botId]);
        
        if (!$dialogo || empty($dialogo->estado_json)) {
            return self::getEmptyContext();
        }
        
        $context = json_decode($dialogo->estado_json, true);
        
        // Validar estructura y limpiar si es necesario
        if (!is_array($context) || !isset($context['current_intent'])) {
            return self::getEmptyContext();
        }
        
        // Limpiar contexto expirado
        $context = self::cleanExpired($context);
        
        return $context;
    }
    
    /**
     * Guardar contexto en el diálogo
     * @param int|string $userId ID del usuario
     * @param array $context Contexto estructurado
     * @param string $botId ID del bot (default: 'BOT')
     * @return bool
     */
    public static function save($userId, $context, $botId = 'BOT')
    {
        $dialogo = Dialogo::findOne(['usuario_id' => $userId, 'bot_id' => $botId]);
        
        if (!$dialogo) {
            $dialogo = new Dialogo([
                'usuario_id' => $userId,
                'bot_id' => $botId,
            ]);
        }
        
        // Limpiar contexto antes de guardar
        $context = self::cleanExpired($context);
        
        $dialogo->estado_json = json_encode($context);
        
        return $dialogo->save();
    }
    
    /**
     * Fusionar parámetros nuevos con contexto existente
     * @param array $currentContext Contexto actual
     * @param string $newIntent Nuevo intent detectado
     * @param array $newParameters Parámetros nuevos extraídos
     * @return array Contexto fusionado
     */
    public static function merge($currentContext, $newIntent, $newParameters)
    {
        // Si es el mismo intent, mantener parámetros existentes y actualizar/agregar nuevos
        if (isset($currentContext['current_intent']) && $currentContext['current_intent'] === $newIntent) {
            $currentContext['parameters'] = array_merge(
                $currentContext['parameters'] ?? [],
                $newParameters
            );
            $currentContext['metadata']['last_update'] = date('Y-m-d H:i:s');
            $currentContext['metadata']['messages_count'] = ($currentContext['metadata']['messages_count'] ?? 0) + 1;
        } else {
            // Nuevo intent: limpiar parámetros del intent anterior
            $currentContext = [
                'current_intent' => $newIntent,
                'current_category' => self::getCategoryFromIntent($newIntent),
                'parameters' => $newParameters,
                'metadata' => [
                    'last_update' => date('Y-m-d H:i:s'),
                    'intent_started_at' => date('Y-m-d H:i:s'),
                    'messages_count' => 1
                ],
                'flags' => [
                    'needs_confirmation' => false,
                    'awaiting_input' => null,
                    'completed' => false
                ]
            ];
        }
        
        return $currentContext;
    }
    
    /**
     * Limpiar contexto expirado
     * @param array $context Contexto a limpiar
     * @return array Contexto limpio
     */
    public static function cleanExpired($context)
    {
        if (!isset($context['current_intent'])) {
            return self::getEmptyContext();
        }
        
        $intent = $context['current_intent'];
        $intentConfig = self::getIntentConfig($intent);
        
        if (!$intentConfig) {
            return self::getEmptyContext();
        }
        
        $lifetime = $intentConfig['lifetime'] ?? 600; // Default 10 minutos
        $lastUpdate = $context['metadata']['last_update'] ?? null;
        
        // Si expiró, limpiar
        if ($lastUpdate) {
            $lastUpdateTime = strtotime($lastUpdate);
            $expireTime = $lastUpdateTime + $lifetime;
            
            if (time() > $expireTime) {
                Yii::info("ConversationContext: Contexto expirado para intent '{$intent}'", 'conversation-context');
                return self::getEmptyContext();
            }
        }
        
        // Limpiar parámetros no relevantes según configuración
        if (isset($context['parameters']) && is_array($context['parameters'])) {
            $requiredParams = $intentConfig['required_params'] ?? [];
            $optionalParams = $intentConfig['optional_params'] ?? [];
            $allowedParams = array_merge($requiredParams, $optionalParams);
            
            foreach ($context['parameters'] as $key => $value) {
                if (!in_array($key, $allowedParams)) {
                    unset($context['parameters'][$key]);
                }
            }
        }
        
        return $context;
    }
    
    /**
     * Construir prompt mínimo para IA
     * @param array $context Contexto actual
     * @param string $userMessage Mensaje del usuario
     * @return string Prompt mínimo
     */
    public static function buildMinimalPrompt($context, $userMessage)
    {
        $intent = $context['current_intent'] ?? 'unknown';
        $parameters = $context['parameters'] ?? [];
        
        // Construir prompt con solo parámetros relevantes
        $promptParts = [];
        
        if (!empty($parameters)) {
            $paramsText = [];
            foreach ($parameters as $key => $value) {
                if ($value !== null && $value !== '') {
                    $paramsText[] = "{$key}: {$value}";
                }
            }
            if (!empty($paramsText)) {
                $promptParts[] = "Contexto: " . implode(', ', $paramsText);
            }
        }
        
        $promptParts[] = "Mensaje del usuario: \"{$userMessage}\"";
        $promptParts[] = "Intent actual: {$intent}";
        
        return implode("\n", $promptParts);
    }
    
    /**
     * Obtener contexto vacío
     * @return array
     */
    private static function getEmptyContext()
    {
        return [
            'current_intent' => null,
            'current_category' => null,
            'parameters' => [],
            'metadata' => [
                'last_update' => null,
                'intent_started_at' => null,
                'messages_count' => 0
            ],
            'flags' => [
                'needs_confirmation' => false,
                'awaiting_input' => null,
                'completed' => false
            ]
        ];
    }
    
    /**
     * Obtener categoría desde intent
     * @param string $intent
     * @return string|null
     */
    private static function getCategoryFromIntent($intent)
    {
        $categories = require Yii::getAlias('@common/config/chatbot/intent-categories.php');
        
        foreach ($categories as $categoryKey => $category) {
            foreach ($category['intents'] as $intentKey => $intentConfig) {
                if ($intentKey === $intent) {
                    return $categoryKey;
                }
            }
        }
        
        return null;
    }
    
    /**
     * Obtener configuración de intent
     * @param string $intent
     * @return array|null
     */
    private static function getIntentConfig($intent)
    {
        $intentParams = require Yii::getAlias('@common/config/chatbot/intent-parameters.php');
        return $intentParams[$intent] ?? null;
    }
    
    /**
     * Marcar intent como completado
     * @param array $context
     * @return array
     */
    public static function markCompleted($context)
    {
        $context['flags']['completed'] = true;
        $context['flags']['awaiting_input'] = null;
        $context['metadata']['last_update'] = date('Y-m-d H:i:s');
        
        return $context;
    }
    
    /**
     * Establecer campo que se está esperando
     * @param array $context
     * @param string $field Campo que falta
     * @return array
     */
    public static function setAwaitingInput($context, $field)
    {
        $context['flags']['awaiting_input'] = $field;
        $context['flags']['completed'] = false;
        
        return $context;
    }
}
