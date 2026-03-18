<?php

namespace common\components\Chatbot\IntentHandlers\Handlers;

class EmergenciasHandler extends BaseIntentHandler
{
    public function handle($intent, $message, $parameters, $context, $userId = null)
    {
        $this->log('handle', ['intent' => $intent, 'priority' => 'CRITICAL']);

        switch ($intent) {
            case 'emergencia_critica':
                return $this->handleEmergenciaCritica($message, $parameters, $context, $userId);

            case 'guardia':
                return $this->handleGuardia($message, $parameters, $context, $userId);

            case 'telefonos_emergencia':
                return $this->handleTelefonosEmergencia($message, $parameters, $context, $userId);

            default:
                return $this->generateErrorResponse("Intent '{$intent}' no manejado por EmergenciasHandler");
        }
    }

    private function handleEmergenciaCritica($message, $parameters, $context, $userId)
    {
        $respuesta = "🚨 EMERGENCIA MÉDICA 🚨\n\n";
        $respuesta .= "Si es una emergencia de vida o muerte, llamá INMEDIATAMENTE al 107 o 911.\n\n";
        $respuesta .= "Si podés, dirigite a la guardia del hospital más cercano.\n\n";
        $respuesta .= "Teléfonos de emergencia:\n";
        $respuesta .= "• 107 - Emergencias médicas\n";
        $respuesta .= "• 911 - Emergencias generales\n\n";
        $respuesta .= "¿Necesitás que te indique el hospital más cercano?";

        return [
            'success' => true,
            'needs_more_info' => false,
            'priority' => 'critical',
            'response' => [
                'text' => $respuesta,
                'data' => [
                    'emergency' => true,
                    'requires_immediate_attention' => true,
                ],
            ],
            'actions' => [
                [
                    'title' => 'Llamar 107',
                    'action' => 'call',
                    'phone' => '107',
                ],
                [
                    'title' => 'Llamar 911',
                    'action' => 'call',
                    'phone' => '911',
                ],
                [
                    'title' => 'Buscar hospital más cercano',
                    'action' => 'buscar_efector',
                    'filter' => ['tipo' => 'hospital', 'tiene_guardia' => true],
                ],
            ],
            'suggestions' => [],
        ];
    }

    private function handleGuardia($message, $parameters, $context, $userId)
    {
        $respuesta = "Información sobre guardias:\n\n";
        $respuesta .= "Las guardias están disponibles 24 horas en los hospitales.\n\n";
        $respuesta .= "¿Querés que busque el hospital con guardia más cercano a tu ubicación?";

        return $this->generateSuccessResponse($respuesta, [], [
            [
                'title' => 'Buscar hospital con guardia',
                'action' => 'buscar_efector',
                'filter' => ['tiene_guardia' => true],
            ],
        ]);
    }

    private function handleTelefonosEmergencia($message, $parameters, $context, $userId)
    {
        $respuesta = "📞 Teléfonos de Emergencia:\n\n";
        $respuesta .= "• 107 - Emergencias médicas (SAM)\n";
        $respuesta .= "• 911 - Emergencias generales\n";
        $respuesta .= "• 100 - Bomberos\n";
        $respuesta .= "• 101 - Policía\n\n";
        $respuesta .= "Para emergencias médicas, llamá al 107.";

        return $this->generateSuccessResponse($respuesta, [], [
            [
                'title' => 'Llamar 107',
                'action' => 'call',
                'phone' => '107',
            ],
            [
                'title' => 'Llamar 911',
                'action' => 'call',
                'phone' => '911',
            ],
        ]);
    }
}

