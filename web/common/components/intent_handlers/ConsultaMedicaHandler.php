<?php

namespace common\components\intent_handlers;

use Yii;

/**
 * Handler para consultas médicas informativas
 */
class ConsultaMedicaHandler extends BaseIntentHandler
{
    /**
     * Procesar intent de consulta médica
     */
    public function handle($intent, $message, $parameters, $context, $userId = null)
    {
        $this->log('handle', ['intent' => $intent]);
        
        switch ($intent) {
            case 'consulta_sintomas':
                return $this->handleConsultaSintomas($message, $parameters, $context, $userId);
            
            case 'consulta_medicamento':
                return $this->handleConsultaMedicamento($message, $parameters, $context, $userId);
            
            case 'consulta_prevencion':
                return $this->handleConsultaPrevencion($message, $parameters, $context, $userId);
            
            case 'consulta_vacunacion':
                return $this->handleConsultaVacunacion($message, $parameters, $context, $userId);
            
            case 'cuando_consultar':
                return $this->handleCuandoConsultar($message, $parameters, $context, $userId);
            
            default:
                return $this->generateErrorResponse("Intent '{$intent}' no manejado por ConsultaMedicaHandler");
        }
    }
    
    private function handleConsultaSintomas($message, $parameters, $context, $userId)
    {
        $sintoma = $parameters['sintoma'] ?? $message;
        
        // Usar IA para generar respuesta informativa
        $prompt = $this->buildMinimalPrompt($message, $context);
        $prompt .= "\n\nProporciona información general sobre este síntoma y cuándo es necesario consultar a un médico.";
        
        try {
            $iaResponse = Yii::$app->iamanager->consultar($prompt, 'consulta-sintomas', 'text-generation');
            
            if ($iaResponse && is_string($iaResponse)) {
                $respuesta = $iaResponse;
            } else {
                $respuesta = "Sobre los síntomas que mencionás, te recomiendo consultar con un médico para una evaluación adecuada. ";
                $respuesta .= "Si los síntomas son graves o persisten, no dudes en acudir a la guardia.";
            }
        } catch (\Exception $e) {
            Yii::error("ConsultaMedicaHandler: Error en IA: " . $e->getMessage(), 'consulta-medica-handler');
            $respuesta = "Te recomiendo consultar con un médico para evaluar tus síntomas adecuadamente.";
        }
        
        $respuesta .= "\n\n¿Querés sacar un turno para consultar?";
        
        return $this->generateSuccessResponse($respuesta, ['sintoma' => $sintoma], [
            [
                'title' => 'Sacar turno',
                'action' => 'crear_turno'
            ]
        ]);
    }
    
    private function handleConsultaMedicamento($message, $parameters, $context, $userId)
    {
        $medicamento = $parameters['medicamento'] ?? null;
        
        if (!$medicamento) {
            return $this->generateErrorResponse('¿Qué medicamento querés consultar?');
        }
        
        // Usar IA para generar respuesta informativa
        $prompt = "Consulta sobre medicamento: {$medicamento}\n";
        $prompt .= "Mensaje: {$message}\n\n";
        $prompt .= "Proporciona información general sobre el uso de este medicamento, dosis típica, y cuándo consultar a un médico. ";
        $prompt .= "IMPORTANTE: No reemplaza la consulta médica. Si hay dudas, siempre consultar con un profesional.";
        
        try {
            $iaResponse = Yii::$app->iamanager->consultar($prompt, 'consulta-medicamento', 'text-generation');
            
            if ($iaResponse && is_string($iaResponse)) {
                $respuesta = $iaResponse;
            } else {
                $respuesta = "Sobre {$medicamento}, te recomiendo consultar con un médico o farmacéutico antes de tomarlo, ";
                $respuesta .= "especialmente si tenés otras condiciones de salud o estás tomando otros medicamentos.";
            }
        } catch (\Exception $e) {
            Yii::error("ConsultaMedicaHandler: Error en IA: " . $e->getMessage(), 'consulta-medica-handler');
            $respuesta = "Te recomiendo consultar con un médico o farmacéutico sobre el uso de {$medicamento}.";
        }
        
        $respuesta .= "\n\n¿Querés consultar con un médico?";
        
        return $this->generateSuccessResponse($respuesta, ['medicamento' => $medicamento], [
            [
                'title' => 'Sacar turno',
                'action' => 'crear_turno'
            ]
        ]);
    }
    
    private function handleConsultaPrevencion($message, $parameters, $context, $userId)
    {
        $respuesta = "Información sobre prevención de salud:\n\n";
        $respuesta .= "• Alimentación saludable y equilibrada\n";
        $respuesta .= "• Actividad física regular\n";
        $respuesta .= "• Control médico periódico\n";
        $respuesta .= "• Vacunación al día\n";
        $respuesta .= "• Evitar hábitos nocivos (tabaco, alcohol en exceso)\n\n";
        $respuesta .= "¿Querés más información sobre algún tema específico?";
        
        return $this->generateSuccessResponse($respuesta);
    }
    
    private function handleConsultaVacunacion($message, $parameters, $context, $userId)
    {
        $respuesta = "Información sobre vacunación:\n\n";
        $respuesta .= "Es importante mantener el calendario de vacunación al día.\n\n";
        $respuesta .= "¿Querés consultar tu calendario de vacunación o sacar un turno para vacunarte?";
        
        return $this->generateSuccessResponse($respuesta, [], [
            [
                'title' => 'Sacar turno para vacunación',
                'action' => 'crear_turno',
                'servicio' => 'VACUNACION'
            ]
        ]);
    }
    
    private function handleCuandoConsultar($message, $parameters, $context, $userId)
    {
        $respuesta = "Debés consultar a un médico cuando:\n\n";
        $respuesta .= "• Los síntomas persisten o empeoran\n";
        $respuesta .= "• Tenés fiebre alta (más de 38°C)\n";
        $respuesta .= "• Dolor intenso o persistente\n";
        $respuesta .= "• Dificultad para respirar\n";
        $respuesta .= "• Signos de deshidratación\n";
        $respuesta .= "• Cualquier síntoma que te preocupe\n\n";
        $respuesta .= "En caso de emergencia, llamá al 107 o 911.\n\n";
        $respuesta .= "¿Querés sacar un turno para consultar?";
        
        return $this->generateSuccessResponse($respuesta, [], [
            [
                'title' => 'Sacar turno',
                'action' => 'crear_turno'
            ],
            [
                'title' => 'Emergencia',
                'action' => 'emergencia_critica'
            ]
        ]);
    }
}
