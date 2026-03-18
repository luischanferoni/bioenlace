<?php

namespace common\components\Chatbot\IntentHandlers\Handlers;

use common\\components\\Chatbot\\ConversationContext;
use common\components\UniversalQueryAgent;

class TurnosHandler extends BaseIntentHandler
{
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

    private function handleCrearTurno($message, $parameters, $context, $userId)
    {
        $missing = $this->getMissingRequiredParams('crear_turno', $parameters);
        if (!empty($missing)) {
            $context = $this->updateContext($userId, $context, 'crear_turno', $parameters);
            $context = ConversationContext::setAwaitingInput($context, $missing[0]);

            return [
                'success' => true,
                'needs_more_info' => true,
                'missing_params' => $missing,
                'response' => [
                    'text' => $this->getQuestionsForParams($missing)[0] ?? 'Necesito más información para crear el turno.',
                    'awaiting' => $missing[0],
                ],
                'suggestions' => $this->getSuggestionsForParams($missing),
                'context_update' => $context,
            ];
        }

        $servicio = $parameters['servicio'] ?? null;
        $fecha = $parameters['fecha'] ?? null;
        $hora = $parameters['hora'] ?? null;
        $profesional = $parameters['profesional'] ?? $parameters['id_rr_hh'] ?? null;

        $query = "crear turno {$servicio}";
        if ($fecha) {
            $query .= " fecha {$fecha}";
        }
        if ($hora) {
            $query .= " hora {$hora}";
        }

        $actionResult = UniversalQueryAgent::processQuery($query, $userId);

        $context = ConversationContext::markCompleted($context);
        $context = $this->updateContext($userId, $context, 'crear_turno', $parameters);

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
                'profesional' => $profesional,
            ],
            $actionResult['data']['actions'] ?? []
        );
    }

    private function handleModificarTurno($message, $parameters, $context, $userId)
    {
        $turnoId = $parameters['turno_id'] ?? null;
        if (!$turnoId) {
            return $this->generateErrorResponse('Necesito saber qué turno querés modificar.');
        }

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
                'campos_modificar' => $camposAModificar,
            ]
        );
    }

    private function handleCancelarTurno($message, $parameters, $context, $userId)
    {
        $turnoId = $parameters['turno_id'] ?? null;
        if (!$turnoId) {
            return $this->generateErrorResponse('Necesito saber qué turno querés cancelar.');
        }

        $context = ConversationContext::markCompleted($context);

        return $this->generateSuccessResponse(
            "¿Confirmás que querés cancelar el turno #{$turnoId}?",
            ['turno_id' => $turnoId]
        );
    }

    private function handleConsultarTurnos($message, $parameters, $context, $userId)
    {
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

    protected function getSuggestionsForParams($params)
    {
        $suggestions = [];

        if (in_array('servicio', $params)) {
            $servicios = \common\models\Servicio::getServiciosConTurnos();
            $limit = 8;
            foreach ($servicios as $s) {
                $suggestions[] = $s->nombre;
                if (count($suggestions) >= $limit) {
                    break;
                }
            }
        }

        return $suggestions;
    }
}

