<?php

namespace common\components\intent_handlers;

use Yii;
use common\components\ConversationContext;
use common\components\UniversalQueryAgent;

/**
 * Handler para intents relacionados con turnos
 */
class TurnosHandler extends BaseIntentHandler
{
    /**
     * Procesar intent de turnos
     */
    public function handle($intent, $message, $parameters, $context, $userId = null)
    {
        $this->log('handle', ['intent' => $intent, 'parameters' => $parameters]);
        
        switch ($intent) {
            case 'crear_turno':
                return $this->handleCrearTurno($message, $parameters, $context, $userId);
            
            case 'modificar_turno':
                return $this->handleModificarTurno($message, $parameters, $context, $userId);
            
            case 'cancelar_turno':
                return $this->handleCancelarTurno($message, $parameters, $context, $userId);
            
            case 'consultar_turnos':
                return $this->handleConsultarTurnos($message, $parameters, $context, $userId);
            
            case 'disponibilidad_turnos':
                return $this->handleDisponibilidadTurnos($message, $parameters, $context, $userId);
            
            default:
                return $this->generateErrorResponse("Intent '{$intent}' no manejado por TurnosHandler");
        }
    }
    
    /**
     * Manejar creación de turno
     */
    private function handleCrearTurno($message, $parameters, $context, $userId)
    {
        // Verificar parámetros requeridos
        $missing = $this->getMissingRequiredParams('crear_turno', $parameters);
        
        if (!empty($missing)) {
            // Actualizar contexto con lo que tenemos
            $context = $this->updateContext($userId, $context, 'crear_turno', $parameters);
            $context = ConversationContext::setAwaitingInput($context, $missing[0]);
            
            return [
                'success' => true,
                'needs_more_info' => true,
                'missing_params' => $missing,
                'response' => [
                    'text' => $this->getQuestionsForParams($missing)[0] ?? 'Necesito más información para crear el turno.',
                    'awaiting' => $missing[0]
                ],
                'suggestions' => $this->getSuggestionsForParams($missing),
                'context_update' => $context
            ];
        }
        
        // Todos los parámetros están presentes
        $servicio = $parameters['servicio'] ?? null;
        $fecha = $parameters['fecha'] ?? null;
        $hora = $parameters['hora'] ?? null;
        $profesional = $parameters['profesional'] ?? $parameters['id_rr_hh'] ?? null;
        
        // Usar UniversalQueryAgent para encontrar acción de crear turno
        $query = "crear turno {$servicio}";
        if ($fecha) {
            $query .= " fecha {$fecha}";
        }
        if ($hora) {
            $query .= " hora {$hora}";
        }
        
        $actionResult = UniversalQueryAgent::processQuery($query, $userId);
        
        // Marcar como completado
        $context = ConversationContext::markCompleted($context);
        $context = $this->updateContext($userId, $context, 'crear_turno', $parameters);
        
        // Generar respuesta
        $responseText = "Perfecto, voy a crear tu turno para {$servicio}";
        if ($fecha) {
            $responseText .= " el {$fecha}";
        }
        if ($hora) {
            $responseText .= " a las {$hora}";
        }
        $responseText .= ". ¿Confirmás?";
        
        return $this->generateSuccessResponse(
            $responseText,
            [
                'servicio' => $servicio,
                'fecha' => $fecha,
                'hora' => $hora,
                'profesional' => $profesional
            ],
            $actionResult['data']['actions'] ?? []
        );
    }
    
    /**
     * Manejar modificación de turno
     */
    private function handleModificarTurno($message, $parameters, $context, $userId)
    {
        $turnoId = $parameters['turno_id'] ?? null;
        
        if (!$turnoId) {
            return $this->generateErrorResponse('Necesito saber qué turno querés modificar.');
        }
        
        // Verificar qué campos quiere modificar
        $camposAModificar = [];
        if (isset($parameters['fecha'])) {
            $camposAModificar[] = 'fecha';
        }
        if (isset($parameters['hora'])) {
            $camposAModificar[] = 'hora';
        }
        if (isset($parameters['profesional'])) {
            $camposAModificar[] = 'profesional';
        }
        
        if (empty($camposAModificar)) {
            return $this->generateErrorResponse('¿Qué querés modificar del turno? (fecha, hora o profesional)');
        }
        
        $context = ConversationContext::markCompleted($context);
        
        return $this->generateSuccessResponse(
            "Voy a modificar tu turno. Cambios: " . implode(', ', $camposAModificar),
            [
                'turno_id' => $turnoId,
                'campos_modificar' => $camposAModificar
            ]
        );
    }
    
    /**
     * Manejar cancelación de turno
     */
    private function handleCancelarTurno($message, $parameters, $context, $userId)
    {
        $turnoId = $parameters['turno_id'] ?? null;
        
        if (!$turnoId) {
            return $this->generateErrorResponse('Necesito saber qué turno querés cancelar.');
        }
        
        $context = ConversationContext::markCompleted($context);
        
        return $this->generateSuccessResponse(
            "¿Confirmás que querés cancelar el turno #{$turnoId}?",
            [
                'turno_id' => $turnoId
            ]
        );
    }
    
    /**
     * Manejar consulta de turnos
     */
    private function handleConsultarTurnos($message, $parameters, $context, $userId)
    {
        // Usar UniversalQueryAgent para buscar turnos
        $query = "mis turnos";
        if (isset($parameters['fecha_desde'])) {
            $query .= " desde {$parameters['fecha_desde']}";
        }
        if (isset($parameters['servicio'])) {
            $query .= " servicio {$parameters['servicio']}";
        }
        
        $actionResult = UniversalQueryAgent::processQuery($query, $userId);
        
        $context = ConversationContext::markCompleted($context);
        
        return $this->generateSuccessResponse(
            "Aquí están tus turnos:",
            [],
            $actionResult['data']['actions'] ?? []
        );
    }
    
    /**
     * Manejar disponibilidad de turnos
     */
    private function handleDisponibilidadTurnos($message, $parameters, $context, $userId)
    {
        $servicio = $parameters['servicio'] ?? null;
        
        if (!$servicio) {
            return $this->generateErrorResponse('¿Para qué servicio querés consultar disponibilidad?');
        }
        
        $query = "disponibilidad turnos {$servicio}";
        $actionResult = UniversalQueryAgent::processQuery($query, $userId);
        
        $context = ConversationContext::markCompleted($context);
        
        return $this->generateSuccessResponse(
            "Aquí está la disponibilidad de turnos para {$servicio}:",
            ['servicio' => $servicio],
            $actionResult['data']['actions'] ?? []
        );
    }
    
    /**
     * Obtener sugerencias para parámetros (sobrescribir método base)
     */
    protected function getSuggestionsForParams($params)
    {
        $suggestions = [];
        
        if (in_array('servicio', $params)) {
            // Sugerir servicios comunes
            $suggestions[] = 'ODONTOLOGIA';
            $suggestions[] = 'PEDIATRIA';
            $suggestions[] = 'MED CLINICA';
            $suggestions[] = 'GINECOLOGIA';
        }
        
        return $suggestions;
    }
}
