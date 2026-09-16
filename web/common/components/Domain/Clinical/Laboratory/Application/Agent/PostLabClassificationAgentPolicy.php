<?php

namespace common\components\Domain\Clinical\Laboratory\Application\Agent;

/**
 * Política operativa del agente `post-lab-classification` (ex YAML platform/agents).
 */
final class PostLabClassificationAgentPolicy
{
    public const AGENT_ID = 'post-lab-classification';

    /**
     * @return array<string, mixed>
     */
    public static function config(): array
    {
        return [
        'version' => 1,
        'agent_id' => 'post-lab-classification',
        'severity_order' => [
            0 => 'normal',
            1 => 'control',
            2 => 'critical',
        ],
        'analyte_rules' => [
            0 => [
                'id' => 'potassium_critical',
                'loinc' => '2823-3',
                'severity' => 'critical',
                'when' => [
                    'any' => [
                        0 => [
                            'gte' => 6,
                        ],
                        1 => [
                            'interpretation_in' => [
                                0 => 'HH',
                                1 => 'A',
                                2 => 'AA',
                            ],
                        ],
                    ],
                ],
            ],
            1 => [
                'id' => 'glucose_fasting_elevated',
                'loinc' => '2345-7',
                'severity' => 'control',
                'when' => [
                    'gte' => 126,
                ],
            ],
            2 => [
                'id' => 'hba1c_elevated',
                'loinc' => '4548-4',
                'severity' => 'control',
                'when' => [
                    'gte' => 6.5,
                ],
            ],
            3 => [
                'id' => 'creatinine_elevated',
                'loinc' => '2160-0',
                'severity' => 'control',
                'when' => [
                    'gte' => 1.5,
                ],
            ],
        ],
        'default_severity' => 'normal',
        'outcomes' => [
            'critical' => [
                'action' => 'notify_patient_and_staff',
                'patient' => [
                    'title' => 'Resultado de laboratorio importante',
                    'body' => 'Uno de tus estudios requiere atención urgente. Acudí a guardia o contactá a tu equipo de salud de inmediato.',
                ],
                'staff' => [
                    'title' => 'Laboratorio: valor crítico',
                    'body_template' => 'Crítico — {analyte_display}: {value} {unit} ({report_display}).',
                ],
            ],
            'control' => [
                'action' => 'notify_patient',
                'patient' => [
                    'title' => 'Resultado de laboratorio',
                    'body' => 'Tu estudio muestra valores que conviene controlar. Tu equipo de salud te contactará o podés reservar un turno de seguimiento.',
                ],
            ],
            'normal' => [
                'action' => 'notify_patient',
                'patient' => [
                    'title' => 'Resultado de laboratorio',
                    'body' => 'Tus estudios están dentro de rangos esperados. Tu médico los revisará en la próxima consulta.',
                ],
            ],
        ],
    ];
    }
}
