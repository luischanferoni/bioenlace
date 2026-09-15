<?php

namespace common\components\Domain\Clinical\Application\Agent;

/**
 * Política operativa del agente `lab-encounter-link` (ex YAML platform/agents).
 */
final class LabEncounterLinkAgentPolicy
{
    public const AGENT_ID = 'lab-encounter-link';

    /**
     * @return array<string, mixed>
     */
    public static function config(): array
    {
        return [
        'version' => 1,
        'agent_id' => 'lab-encounter-link',
        'search_max_days_before' => 14,
        'search_max_days_after' => 3,
        'min_winner_score' => 35,
        'min_score_gap' => 10,
        'scoring' => [
            'fhir_encounter_ref' => 100,
            'same_day_as_issued' => 40,
            'proximity_per_day' => 3,
            'max_proximity_days' => 14,
            'service_request_lab_match' => 35,
            'same_pes_as_request' => 20,
        ],
        'service_request_categories' => [
            0 => 'laboratory',
            1 => 'lab',
            2 => 'procedure',
        ],
    ];
    }
}
