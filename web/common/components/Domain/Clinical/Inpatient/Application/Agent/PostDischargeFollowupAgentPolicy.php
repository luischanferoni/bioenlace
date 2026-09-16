<?php

namespace common\components\Domain\Clinical\Inpatient\Application\Agent;

/**
 * Política operativa del agente `post-discharge-followup` (ex YAML platform/agents).
 */
final class PostDischargeFollowupAgentPolicy
{
    public const AGENT_ID = 'post-discharge-followup';

    /**
     * @return array<string, mixed>
     */
    public static function config(): array
    {
        return [
        'version' => 1,
        'agent_id' => 'post-discharge-followup',
        'programs' => [
            'default' => [
                'touchpoints' => [
                    0 => [
                        'delay_days' => 1,
                        'title' => 'Primer día en casa',
                        'purpose' => 'recovery',
                        'form_kind' => 'symptoms',
                    ],
                    1 => [
                        'delay_days' => 7,
                        'title' => 'Primera semana en casa',
                        'purpose' => 'evolution',
                        'form_kind' => 'evolution_short',
                    ],
                    2 => [
                        'delay_days' => 30,
                        'title' => 'Un mes del alta',
                        'purpose' => 'adherence',
                        'form_kind' => 'adherence',
                    ],
                ],
            ],
            'cirugia' => [
                'touchpoints' => [
                    0 => [
                        'delay_days' => 1,
                        'title' => 'Post cirugía — día 1',
                        'purpose' => 'recovery',
                        'form_kind' => 'symptoms',
                    ],
                    1 => [
                        'delay_days' => 7,
                        'title' => 'Post cirugía — herida y fiebre',
                        'purpose' => 'evolution',
                        'form_kind' => 'evolution_short',
                    ],
                    2 => [
                        'delay_days' => 30,
                        'title' => 'Control post cirugía',
                        'purpose' => 'evolution',
                        'form_kind' => 'evolution_short',
                    ],
                ],
            ],
        ],
        'rules' => [
            0 => [
                'id' => 'programa_cirugia',
                'when' => [
                    'field' => 'tipo_ingreso_codigo',
                    'in' => [
                        0 => 'cirugia',
                        1 => 'quirurgico',
                        2 => 'post_quirurgico',
                    ],
                ],
                'program' => 'cirugia',
            ],
        ],
    ];
    }
}
