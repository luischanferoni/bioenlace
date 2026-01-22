<?php

namespace common\components\intent_handlers;

use Yii;
use common\components\ConversationContext;
use common\components\ParameterExtractor;

/**
 * Clase base para todos los handlers de intents
 * 
 * Proporciona métodos comunes y estructura base para procesar intents.
 */
abstract class BaseIntentHandler
{
    /**
     * Procesar el intent
     * @param string $intent Nombre del intent
     * @param string $message Mensaje del usuario
     * @param array $parameters Parámetros extraídos
     * @param array $context Contexto de conversación
     * @param int|string|null $userId ID del usuario
     * @return array Respuesta estructurada
     */
    abstract public function handle($intent, $message, $parameters, $context, $userId = null);
    
    /**
     * Verificar si faltan parámetros requeridos
     * @param string $intent
     * @param array $parameters
     * @return array Parámetros faltantes
     */
    protected function getMissingRequiredParams($intent, $parameters)
    {
        $intentConfig = require Yii::getAlias('@common/config/IntentParameters.php');
        
        if (!isset($intentConfig[$intent])) {
            return [];
        }
        
        $requiredParams = $intentConfig[$intent]['required_params'] ?? [];
        $missing = [];
        
        foreach ($requiredParams as $param) {
            if (!isset($parameters[$param]) || empty($parameters[$param])) {
                $missing[] = $param;
            }
        }
        
        return $missing;
    }
    
    /**
     * Generar respuesta cuando faltan parámetros
     * @param array $missingParams Parámetros faltantes
     * @param string $intent
     * @return array
     */
    protected function generateMissingParamsResponse($missingParams, $intent)
    {
        $questions = $this->getQuestionsForParams($missingParams);
        
        return [
            'success' => true,
            'needs_more_info' => true,
            'missing_params' => $missingParams,
            'response' => [
                'text' => implode(' ', $questions),
                'awaiting' => $missingParams[0] ?? null
            ],
            'suggestions' => $this->getSuggestionsForParams($missingParams)
        ];
    }
    
    /**
     * Obtener preguntas para parámetros faltantes
     * @param array $params
     * @return array
     */
    protected function getQuestionsForParams($params)
    {
        $questions = [
            'servicio' => '¿Qué servicio necesitás?',
            'fecha' => '¿Para qué día querés el turno?',
            'hora' => '¿En qué horario te gustaría?',
            'profesional' => '¿Con qué profesional querés el turno?',
            'efector' => '¿En qué centro de salud?',
            'medicamento' => '¿Qué medicamento querés consultar?',
            'sintoma' => '¿Qué síntoma tenés?',
            'turno_id' => '¿Qué turno querés modificar/cancelar?',
            'tipo_practica' => '¿Qué tipo de estudio necesitás?',
            'ubicacion' => '¿En qué zona?'
        ];
        
        $result = [];
        foreach ($params as $param) {
            if (isset($questions[$param])) {
                $result[] = $questions[$param];
            }
        }
        
        return $result;
    }
    
    /**
     * Obtener sugerencias para parámetros
     * @param array $params
     * @return array
     */
    protected function getSuggestionsForParams($params)
    {
        // Por defecto, no hay sugerencias
        // Los handlers específicos pueden sobrescribir este método
        return [];
    }
    
    /**
     * Generar respuesta de éxito
     * @param string $text Texto de respuesta
     * @param array $data Datos adicionales
     * @param array $actions Acciones disponibles
     * @return array
     */
    protected function generateSuccessResponse($text, $data = [], $actions = [])
    {
        return [
            'success' => true,
            'needs_more_info' => false,
            'response' => [
                'text' => $text,
                'data' => $data
            ],
            'actions' => $actions,
            'suggestions' => []
        ];
    }
    
    /**
     * Generar respuesta de error
     * @param string $message Mensaje de error
     * @param array $details Detalles adicionales
     * @return array
     */
    protected function generateErrorResponse($message, $details = [])
    {
        return [
            'success' => false,
            'error' => $message,
            'details' => $details
        ];
    }
    
    /**
     * Actualizar contexto de conversación
     * @param int|string $userId
     * @param array $context
     * @param string $intent
     * @param array $parameters
     * @return array Contexto actualizado
     */
    protected function updateContext($userId, $context, $intent, $parameters)
    {
        $context = ConversationContext::merge($context, $intent, $parameters);
        ConversationContext::save($userId, $context);
        
        return $context;
    }
    
    /**
     * Construir prompt mínimo para IA si es necesario
     * @param string $message
     * @param array $context
     * @return string
     */
    protected function buildMinimalPrompt($message, $context)
    {
        return ConversationContext::buildMinimalPrompt($context, $message);
    }
    
    /**
     * Log de actividad
     * @param string $action
     * @param array $data
     */
    protected function log($action, $data = [])
    {
        $handlerName = static::class;
        Yii::info("{$handlerName}::{$action} - " . json_encode($data, JSON_UNESCAPED_UNICODE), 'intent-handler');
    }
}
