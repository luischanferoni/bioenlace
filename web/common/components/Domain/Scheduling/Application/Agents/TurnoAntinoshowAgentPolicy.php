<?php

namespace common\components\Domain\Scheduling\Application\Agents;

/**
 * Política operativa del agente `turno-antinoshow` (ex YAML platform/agents).
 */
final class TurnoAntinoshowAgentPolicy
{
    public const AGENT_ID = 'turno-antinoshow';

    /**
     * @return array<string, mixed>
     */
    public static function config(): array
    {
        return [
        'version' => 2,
        'agent_id' => 'turno-antinoshow',
        'execution_mode' => 'shadow',
        'required_profile_contract_version' => 1,
        'risk' => [
            'lookback_months' => 6,
            'profile_window_days' => 180,
            'high_min_no_shows' => 2,
            'medium_min_no_shows' => 1,
            'long_lead_days' => 21,
            'first_visit_level' => 'medium',
        ],
        'checkpoints' => [
            0 => [
                'hours_before' => 48,
                'shared_confirmation_request' => true,
                'rules' => [
                    0 => [
                        'id' => 'high_extra_confirm',
                        'when' => [
                            'field' => 'risk_level',
                            'equals' => 'high',
                        ],
                        'action' => 'shared_confirm_evaluate',
                        'schedule_release_hours_before' => 24,
                    ],
                ],
            ],
            1 => [
                'hours_before' => 2,
                'rules' => [
                    0 => [
                        'id' => 'high_reminder',
                        'when' => [
                            'field' => 'risk_level',
                            'equals' => 'high',
                        ],
                        'action' => 'reminder_push',
                    ],
                    1 => [
                        'id' => 'medium_reminder',
                        'when' => [
                            'field' => 'risk_level',
                            'equals' => 'medium',
                        ],
                        'action' => 'reminder_push',
                    ],
                ],
            ],
        ],
        'release_slot' => [
            'enabled' => false,
            'hours_before_appointment' => 24,
            'only_risk_levels' => [
                0 => 'high',
            ],
        ],
        'patient_messages' => [
            'extra_confirm' => [
                'title' => 'Confirmá tu turno',
                'body' => 'Confirmá asistencia al turno del {{fecha}} {{hora}}.',
            ],
            'reminder' => [
                'title' => 'Tu turno es pronto',
                'body' => 'Recordá tu turno del {{fecha}} a las {{hora}}.',
            ],
            'slot_released' => [
                'title' => 'Turno liberado',
                'body' => 'No confirmaste tu turno del {{fecha}}. El cupo quedó disponible; podés reservar otro horario desde la app.',
            ],
        ],
    ];
    }
}
