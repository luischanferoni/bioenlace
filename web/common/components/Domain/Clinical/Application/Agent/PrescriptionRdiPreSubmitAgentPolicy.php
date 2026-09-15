<?php

namespace common\components\Domain\Clinical\Application\Agent;

/**
 * Política operativa del agente `prescription-rdi-pre-submit` (ex YAML platform/agents).
 */
final class PrescriptionRdiPreSubmitAgentPolicy
{
    public const AGENT_ID = 'prescription-rdi-pre-submit';

    /**
     * @return array<string, mixed>
     */
    public static function config(): array
    {
        return [
        'version' => 2,
        'agent_id' => 'prescription-rdi-pre-submit',
        'policy' => [
            'block_duplicate_medication_hours' => 24,
            'min_diagnosis_display_length' => 3,
        ],
    ];
    }
}
