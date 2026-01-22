<?php

namespace common\components\intent_handlers;

use Yii;

/**
 * Handler para intents generales (saludo, ayuda, contacto, fuera de alcance)
 */
class GeneralHandler extends BaseIntentHandler
{
    /**
     * Procesar intent general
     */
    public function handle($intent, $message, $parameters, $context, $userId = null)
    {
        $this->log('handle', ['intent' => $intent]);
        
        switch ($intent) {
            case 'saludo':
                return $this->handleSaludo($message, $parameters, $context, $userId);
            
            case 'ayuda':
                return $this->handleAyuda($message, $parameters, $context, $userId);
            
            case 'contacto':
                return $this->handleContacto($message, $parameters, $context, $userId);
            
            case 'fuera_de_alcance':
                return $this->handleFueraDeAlcance($message, $parameters, $context, $userId);
            
            default:
                return $this->generateErrorResponse("Intent '{$intent}' no manejado por GeneralHandler");
        }
    }
    
    private function handleSaludo($message, $parameters, $context, $userId)
    {
        $saludos = [
            "¡Hola! Soy tu asistente virtual de BioEnlace. ¿En qué puedo ayudarte?",
            "¡Buen día! Estoy aquí para ayudarte con turnos, información de salud, farmacias y más.",
            "¡Hola! ¿Qué necesitás hoy? Puedo ayudarte con turnos médicos, consultas de salud y más."
        ];
        
        $respuesta = $saludos[array_rand($saludos)];
        
        return $this->generateSuccessResponse($respuesta, [], [
            [
                'title' => 'Sacar turno',
                'action' => 'crear_turno'
            ],
            [
                'title' => 'Ver mi historia clínica',
                'action' => 'ver_historia_clinica'
            ],
            [
                'title' => 'Farmacias de turno',
                'action' => 'farmacias_turno'
            ]
        ]);
    }
    
    private function handleAyuda($message, $parameters, $context, $userId)
    {
        $respuesta = "Puedo ayudarte con:\n\n";
        $respuesta .= "• Sacar, modificar o cancelar turnos médicos\n";
        $respuesta .= "• Ver tu historia clínica y consultas anteriores\n";
        $respuesta .= "• Buscar farmacias de turno\n";
        $respuesta .= "• Consultas sobre salud y medicamentos\n";
        $respuesta .= "• Información sobre efectores y profesionales\n";
        $respuesta .= "• Y mucho más. ¿Qué necesitás?";
        
        return $this->generateSuccessResponse($respuesta);
    }
    
    private function handleContacto($message, $parameters, $context, $userId)
    {
        $respuesta = "Para contactar con el sistema de salud:\n\n";
        $respuesta .= "• Teléfono: [Número de contacto]\n";
        $respuesta .= "• Email: [Email de contacto]\n";
        $respuesta .= "• Horario de atención: [Horarios]\n\n";
        $respuesta .= "¿Hay algo más en lo que pueda ayudarte?";
        
        return $this->generateSuccessResponse($respuesta);
    }
    
    private function handleFueraDeAlcance($message, $parameters, $context, $userId)
    {
        $respuesta = "No pude entender tu consulta. Puedo ayudarte con:\n\n";
        $respuesta .= "• Turnos médicos\n";
        $respuesta .= "• Historia clínica\n";
        $respuesta .= "• Farmacias\n";
        $respuesta .= "• Consultas de salud\n";
        $respuesta .= "• Información sobre efectores y profesionales\n\n";
        $respuesta .= "¿Podrías reformular tu consulta?";
        
        return $this->generateSuccessResponse($respuesta, [], [
            [
                'title' => 'Sacar turno',
                'action' => 'crear_turno'
            ],
            [
                'title' => 'Ver mi historia clínica',
                'action' => 'ver_historia_clinica'
            ],
            [
                'title' => 'Farmacias de turno',
                'action' => 'farmacias_turno'
            ]
        ]);
    }
}
