<?php

namespace common\components\intent_handlers;

use Yii;
use common\components\UniversalQueryAgent;

/**
 * Handler para medicamentos y farmacias
 */
class MedicamentosHandler extends BaseIntentHandler
{
    /**
     * Procesar intent de medicamentos
     */
    public function handle($intent, $message, $parameters, $context, $userId = null)
    {
        $this->log('handle', ['intent' => $intent]);
        
        switch ($intent) {
            case 'farmacias_turno':
                return $this->handleFarmaciasTurno($message, $parameters, $context, $userId);
            
            case 'buscar_farmacias':
                return $this->handleBuscarFarmacias($message, $parameters, $context, $userId);
            
            case 'disponibilidad_medicamentos':
                return $this->handleDisponibilidadMedicamentos($message, $parameters, $context, $userId);
            
            default:
                return $this->generateErrorResponse("Intent '{$intent}' no manejado por MedicamentosHandler");
        }
    }
    
    private function handleFarmaciasTurno($message, $parameters, $context, $userId)
    {
        // Usar UniversalQueryAgent para buscar farmacias de turno
        $query = "farmacias de turno ahora";
        if (isset($parameters['ubicacion'])) {
            $query .= " en {$parameters['ubicacion']}";
        }
        
        $actionResult = UniversalQueryAgent::processQuery($query, $userId);
        
        $respuesta = "Aquí están las farmacias de turno disponibles:\n\n";
        $respuesta .= "Las farmacias de turno están abiertas 24 horas para emergencias.\n\n";
        
        if (isset($actionResult['data']['actions']) && !empty($actionResult['data']['actions'])) {
            $respuesta .= "Encontré " . count($actionResult['data']['actions']) . " farmacia(s) de turno.";
        } else {
            $respuesta .= "Te recomiendo llamar a las farmacias para confirmar disponibilidad.";
        }
        
        return $this->generateSuccessResponse(
            $respuesta,
            ['ubicacion' => $parameters['ubicacion'] ?? null],
            $actionResult['data']['actions'] ?? []
        );
    }
    
    private function handleBuscarFarmacias($message, $parameters, $context, $userId)
    {
        $query = "buscar farmacias";
        if (isset($parameters['ubicacion'])) {
            $query .= " en {$parameters['ubicacion']}";
        }
        
        $actionResult = UniversalQueryAgent::processQuery($query, $userId);
        
        $respuesta = "Aquí están las farmacias cercanas:\n\n";
        
        if (isset($actionResult['data']['actions']) && !empty($actionResult['data']['actions'])) {
            $respuesta .= "Encontré " . count($actionResult['data']['actions']) . " farmacia(s).";
        } else {
            $respuesta .= "No encontré farmacias en esa ubicación. ¿Querés buscar en otra zona?";
        }
        
        return $this->generateSuccessResponse(
            $respuesta,
            ['ubicacion' => $parameters['ubicacion'] ?? null],
            $actionResult['data']['actions'] ?? []
        );
    }
    
    private function handleDisponibilidadMedicamentos($message, $parameters, $context, $userId)
    {
        $medicamento = $parameters['medicamento'] ?? null;
        
        if (!$medicamento) {
            return $this->generateErrorResponse('¿Qué medicamento querés consultar?');
        }
        
        $query = "disponibilidad medicamento {$medicamento}";
        $actionResult = UniversalQueryAgent::processQuery($query, $userId);
        
        $respuesta = "Consultando disponibilidad de {$medicamento}...\n\n";
        $respuesta .= "Te recomiendo llamar a las farmacias para confirmar disponibilidad y precio.";
        
        return $this->generateSuccessResponse(
            $respuesta,
            ['medicamento' => $medicamento],
            $actionResult['data']['actions'] ?? []
        );
    }
}
