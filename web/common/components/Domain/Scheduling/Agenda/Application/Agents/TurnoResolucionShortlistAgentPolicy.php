<?php

namespace common\components\Domain\Scheduling\Agenda\Application\Agents;

/**
 * Política operativa del agente `turno-resolucion-shortlist` (ex YAML platform/agents).
 */
final class TurnoResolucionShortlistAgentPolicy
{
    public const AGENT_ID = 'turno-resolucion-shortlist';

    /**
     * @return array<string, mixed>
     */
    public static function config(): array
    {
        return [
        'version' => 1,
        'agent_id' => 'turno-resolucion-shortlist',
        'max_options' => 3,
        'candidate_pool_max' => 40,
        'search_max_dias' => 14,
        'scoring' => [
            'same_pes' => 30,
            'neighbor_option' => 25,
            'same_date_as_original' => 15,
            'proximity_per_day' => 2,
            'max_proximity_days' => 14,
        ],
        'push' => [
            'body_suffix_template' => ' Opciones sugeridas: {{options_summary}}',
        ],
    ];
    }
}
