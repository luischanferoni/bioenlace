<?php

namespace common\components\Domain\Organization\Pes\Domain\Catalog;

/**
 * Catálogo de dominio (ex metadata/bioenlace/organization/agenda-by-encounter-class.yaml).
 */
final class AgendaByEncounterClassCatalog
{
    /**
     * @return array<string, mixed>
     */
    public static function config(): array
    {
        return [
        'version' => 1,
        'kinds' => [
            'AMB' => [
                'storage' => 'pes_slot_agenda',
                'capacity_model' => 'weekly_slots',
                'patient_booking' => true,
                'label' => 'Ambulatorio (cupos)',
                'description' => 'Grilla semanal por PES con intervalo fijo. Única agenda expuesta a pacientes para solicitar turnos.
',
            ],
            'EMER' => [
                'storage' => 'horario_interval',
                'capacity_model' => 'weekly_presence',
                'patient_booking' => false,
                'service_filter' => 'optional',
                'label' => 'Urgencia / guardia (horarios)',
                'description' => 'Plantilla semanal de horarios (entrada/salida por día) materializada a intervalos. Servicio opcional como filtro de ámbito. No genera cupos ni filas en turnos de paciente.
',
            ],
            'IMP' => [
                'storage' => 'horario_interval',
                'capacity_model' => 'weekly_presence',
                'patient_booking' => false,
                'service_filter' => 'optional',
                'label' => 'Internación (horarios)',
                'description' => 'Horario de piso con patrón semanal materializado. No genera cupos ni filas en turnos de paciente.
',
            ],
        ],
        'conflicts' => [
            'horario_overlap_same_persona_efector' => true,
            'horario_vs_amb_slots' => true,
        ],
        'patient_exposure' => [
            'slot_finder_encounter_classes' => [
                0 => 'AMB',
            ],
            'forbid_horario_in_turnos' => true,
        ],
        'operational' => [
            'emer_assign_requires_horario' => true,
            'emer_assign_allow_without_any_presence' => false,
            'imp_view_requires_horario' => true,
        ],
    ];
    }
}
