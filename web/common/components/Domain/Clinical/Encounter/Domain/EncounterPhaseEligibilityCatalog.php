<?php

namespace common\components\Domain\Clinical\Encounter\Domain;

/**
 * Catálogo de dominio (ex Clinical/metadata/encounter_phase_eligibility.yaml).
 */
final class EncounterPhaseEligibilityCatalog
{
    /**
     * @return array<string, mixed>
     */
    public static function config(): array
    {
        return [
        'version' => 1,
        'phases' => [
            'motivos_consulta' => [
                'label' => 'Contanos tus motivos de consulta',
                'surface' => 'chat_motivos',
                'skip_when_any' => [
                    0 => [
                        'field' => 'encounter_id',
                        'empty' => true,
                    ],
                    1 => [
                        'field' => 'encounter_class',
                        'not_in' => [
                            0 => 'AMB',
                        ],
                    ],
                    2 => [
                        'field' => 'encounter_parent_type',
                        'in' => [
                            0 => 'SOLICITUD_ASYNC',
                        ],
                    ],
                    3 => [
                        'field' => 'tipo_atencion',
                        'in' => [
                            0 => 'async',
                        ],
                    ],
                    4 => [
                        'field' => 'turno_estado',
                        'in' => [
                            0 => 'CANCELADO',
                            1 => 'EN_RESOLUCION',
                        ],
                    ],
                ],
            ],
            'asistencia_pre_consulta' => [
                'label' => 'Cuestionario pre-consulta',
                'surface' => 'flow',
                'intent_id' => 'care-packs.asistencia-pre-consulta-flow',
                'action_id' => 'care-packs.assistance',
                'skip_when_any' => [
                    0 => [
                        'field' => 'care_cohort_enabled',
                        'equals' => false,
                    ],
                    1 => [
                        'field' => 'encounter_id',
                        'empty' => true,
                    ],
                    2 => [
                        'field' => 'encounter_class',
                        'not_in' => [
                            0 => 'AMB',
                        ],
                    ],
                    3 => [
                        'field' => 'encounter_parent_type',
                        'in' => [
                            0 => 'SOLICITUD_ASYNC',
                        ],
                    ],
                    4 => [
                        'field' => 'sin_pack_assistance',
                        'equals' => true,
                    ],
                    5 => [
                        'field' => 'asistencia_completada',
                        'equals' => true,
                    ],
                    6 => [
                        'field' => 'turno_estado',
                        'in' => [
                            0 => 'CANCELADO',
                            1 => 'EN_RESOLUCION',
                        ],
                    ],
                ],
            ],
            'post_consulta' => [
                'label' => 'Seguimiento post-consulta',
                'surface' => 'pack_followup',
                'skip_when_any' => [
                    0 => [
                        'field' => 'care_cohort_enabled',
                        'equals' => false,
                    ],
                    1 => [
                        'field' => 'encounter_id',
                        'empty' => true,
                    ],
                    2 => [
                        'field' => 'encounter_finished',
                        'equals' => false,
                    ],
                    3 => [
                        'field' => 'sin_pack_followup',
                        'equals' => true,
                    ],
                    4 => [
                        'field' => 'encounter_class',
                        'not_in' => [
                            0 => 'AMB',
                        ],
                    ],
                ],
            ],
        ],
    ];
    }
}
