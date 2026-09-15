<?php

namespace common\components\Domain\Scheduling\Application\Agent;

/**
 * Política operativa del agente `turno-resolucion-auto-reserva` (ex YAML platform/agents).
 */
final class TurnoResolucionAutoReservaAgentPolicy
{
    public const AGENT_ID = 'turno-resolucion-auto-reserva';

    /**
     * @return array<string, mixed>
     */
    public static function config(): array
    {
        return [
        'version' => 1,
        'agent_id' => 'turno-resolucion-auto-reserva',
        'search_max_dias' => 14,
        'candidate_pool_max' => 40,
        'min_winner_score' => 40,
        'min_score_gap' => 8,
        'scoring' => [
            'preference_franja_match' => 15,
            'preference_dia_match' => 10,
            'preference_tipo_atencion_match' => 15,
        ],
        'patient_messages' => [
            'auto_rebooked' => [
                'title' => 'Te reubicamos el turno',
                'body' => 'Reservamos tu turno para el {{fecha}} a las {{hora}}. Si no te sirve, cambiá el horario desde la app.',
            ],
        ],
    ];
    }
}
