<?php

namespace common\components\Domain\Scheduling\Application\Agent;

/**
 * Política operativa del agente `turno-resolucion-loop-close` (ex YAML platform/agents).
 */
final class TurnoResolucionLoopCloseAgentPolicy
{
    public const AGENT_ID = 'turno-resolucion-loop-close';

    /**
     * @return array<string, mixed>
     */
    public static function config(): array
    {
        return [
        'version' => 1,
        'agent_id' => 'turno-resolucion-loop-close',
        'loop_close_hours' => 72,
        'default_action' => 'cancel_turno',
        'rules' => [
            0 => [
                'id' => 'chronic_escalate_coordination',
                'when' => [
                    'field' => 'urgency_band',
                    'in' => [
                        0 => 'C',
                        1 => 'D',
                    ],
                ],
                'action' => 'escalate_staff',
                'staff' => [
                    'title' => 'Resolución sin respuesta — seguimiento prioritario',
                    'body_template' => 'Turno del {{fecha}} sin reubicar tras el plazo de contacto. Paciente banda {{urgency_band}}.',
                ],
            ],
        ],
        'patient_messages' => [
            'cancel_turno' => [
                'title' => 'Turno liberado',
                'body' => 'No recibimos respuesta para reubicar tu turno del {{fecha}}. Volvé a reservar cuando puedas desde la app.',
            ],
            'keep_in_resolution' => [
                'title' => 'Seguimos con tu turno',
                'body' => 'Aún estamos gestionando un nuevo horario para tu turno del {{fecha}}. Te avisaremos cuando haya novedades.',
            ],
        ],
    ];
    }
}
