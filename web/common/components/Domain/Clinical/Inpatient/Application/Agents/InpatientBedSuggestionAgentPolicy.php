<?php

namespace common\components\Domain\Clinical\Inpatient\Application\Agents;

/**
 * Política operativa del agente `internacion-cama-sugerencia` (ex YAML platform/agents).
 */
final class InpatientBedSuggestionAgentPolicy
{
    public const AGENT_ID = 'internacion-cama-sugerencia';

    /**
     * @return array<string, mixed>
     */
    public static function config(): array
    {
        return [
        'version' => 1,
        'agent_id' => 'internacion-cama-sugerencia',
        'scoring' => [
            'respirador_required' => 45,
            'monitor_bonus' => 8,
            'sala_servicio_match' => 30,
            'covid_aislamiento_match' => 35,
            'pediatria_match' => 40,
            'estado_libre_bonus' => 10,
        ],
        'top_n' => 5,
    ];
    }
}
