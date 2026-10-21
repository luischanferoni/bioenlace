<?php

namespace common\components\Domain\Clinical\Encounter\Domain;

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
            ],
            'asistencia_pre_consulta' => [
                'anchor' => 'turno_start',
                'open_offset' => 'param:encounter_journey_preparar_minutos_antes',
                'close_offset' => 'param:motivos_consulta_cierre_minutos',
            ],
            'post_consulta' => [
                'anchor' => 'encounter_finished',
                'open_offset' => '0h',
                'close_offset' => '+30d',
            ],
        ],
    ];
    }
}
