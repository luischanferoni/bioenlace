<?php

namespace common\components\Domain\Clinical\CareCohort\Application\Agents;

/**
 * Política operativa del agente `care-followup-branching` (ex YAML platform/agents).
 */
final class CareFollowupBranchingAgentPolicy
{
    public const AGENT_ID = 'care-followup-branching';

    /**
     * @return array<string, mixed>
     */
    public static function config(): array
    {
        return [
        'version' => 1,
        'agent_id' => 'care-followup-branching',
        'rules' => [
            0 => [
                'id' => 'worsening_evolution',
                'form_kinds' => [
                    0 => 'evolution_short',
                ],
                'when' => [
                    'field' => 'comparacion',
                    'equals' => 'peor',
                ],
                'action' => 'notify_staff',
            ],
            1 => [
                'id' => 'poor_adherence',
                'form_kinds' => [
                    0 => 'adherence',
                ],
                'when' => [
                    'field' => 'tomo_medicacion',
                    'equals' => 'no',
                ],
                'action' => 'educational_push',
            ],
            2 => [
                'id' => 'high_symptom_intensity',
                'form_kinds' => [
                    0 => 'symptoms',
                ],
                'when' => [
                    'field' => 'intensidad',
                    'gte' => 8,
                ],
                'action' => 'notify_staff',
            ],
        ],
    ];
    }
}
