<?php

namespace common\components\Domain\Clinical\Domain;

/**
 * Catálogo de dominio (ex Clinical/metadata/encounter_phase_windows.yaml).
 */
final class EncounterPhaseWindowsCatalog
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
                'anchor' => 'turno_start',
                'open_offset' => 'param:encounter_journey_preparar_minutos_antes',
                'close_offset' => 'param:motivos_consulta_cierre_minutos',
                'notifications' => [
                    0 => [
                        'offset' => 'param:encounter_journey_preparar_minutos_antes',
                        'tipo' => 'JOURNEY_MOTIVOS_RECORDATORIO',
                        'title' => 'Prepará tu consulta',
                        'body' => 'Ya podés contarnos tus motivos de consulta para el turno del {fecha}.',
                    ],
                    1 => [
                        'offset' => '-2h',
                        'tipo' => 'JOURNEY_MOTIVOS_ULTIMO_AVISO',
                        'title' => 'Últimas horas para cargar motivos',
                        'body' => 'Tu turno es pronto. Podés cargar motivos hasta poco antes del horario.',
                    ],
                ],
            ],
            'asistencia_pre_consulta' => [
                'anchor' => 'turno_start',
                'open_offset' => 'param:encounter_journey_preparar_minutos_antes',
                'close_offset' => 'param:motivos_consulta_cierre_minutos',
                'notifications' => [
                    0 => [
                        'offset' => 'param:encounter_journey_preparar_minutos_antes',
                        'tipo' => 'JOURNEY_PRECONSULTA_RECORDATORIO',
                        'title' => 'Cuestionario pre-consulta',
                        'body' => 'Completá el cuestionario antes de tu turno del {fecha}.',
                    ],
                ],
            ],
            'post_consulta' => [
                'anchor' => 'encounter_finished',
                'open_offset' => '0h',
                'close_offset' => '+30d',
                'notifications' => [
                    0 => [
                        'offset' => '+0h',
                        'tipo' => 'JOURNEY_POSTCONSULTA_DISPONIBLE',
                        'title' => 'Seguimiento post-consulta',
                        'body' => 'Podés contarnos cómo seguís después de tu atención del {fecha}.',
                    ],
                    1 => [
                        'offset' => '+7d',
                        'tipo' => 'JOURNEY_POSTCONSULTA_RECORDATORIO',
                        'title' => 'Recordatorio de seguimiento',
                        'body' => 'Revisá si tenés formularios pendientes de tu consulta del {fecha}.',
                    ],
                ],
            ],
        ],
    ];
    }
}
