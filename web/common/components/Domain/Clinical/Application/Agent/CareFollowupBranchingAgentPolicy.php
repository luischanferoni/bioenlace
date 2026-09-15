<?php

namespace common\components\Domain\Clinical\Application\Agent;

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
                'staff' => [
                    'title' => 'Seguimiento: posible empeoramiento',
                    'body_template' => 'El paciente reportó señales de empeoramiento en «{touchpoint_title}». Revisá el seguimiento post-consulta.',
                ],
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
                'patient' => [
                    'title' => 'Recordatorio de medicación',
                    'body' => 'Es importante continuar el tratamiento indicado. Si tenés dificultades o efectos adversos, contactá a tu equipo de salud.',
                ],
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
                'staff' => [
                    'title' => 'Seguimiento: síntomas intensos',
                    'body_template' => 'El paciente indicó intensidad alta ({intensidad}/10) en «{touchpoint_title}».',
                ],
            ],
        ],
    ];
    }
}
