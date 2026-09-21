<?php

namespace common\components\Domain\Scheduling\BehaviorProfile\Domain\Catalog;

/**
 * Catálogo de dominio (ex metadata/bioenlace/scheduling/turno-behavior-profile.yaml).
 */
final class TurnoBehaviorProfileCatalog
{
    /**
     * @return array<string, mixed>
     */
    public static function config(): array
    {
        return [
        'version' => 1,
        'contract_id' => 'turno-behavior-profile',
        'windows_days' => [
            0 => 90,
            1 => 180,
            2 => 365,
        ],
        'scopes' => [
            0 => 'GLOBAL',
            1 => 'EFECTOR',
            2 => 'SERVICIO',
            3 => 'MODALIDAD',
        ],
        'actors' => [
            0 => 'PACIENTE',
            1 => 'REPRESENTANTE',
            2 => 'STAFF',
            3 => 'EFECTOR',
            4 => 'SISTEMA',
            5 => 'EXTERNO',
        ],
        'patient_attributed_actors' => [
            0 => 'PACIENTE',
            1 => 'REPRESENTANTE',
        ],
        'attribution_qualities' => [
            0 => 'NATIVE',
        ],
        'min_sample_size' => 5,
        'late_cancellation' => [
            'hours_before_appointment' => 24,
        ],
        'events' => [
            0 => 'APPOINTMENT_CREATED',
            1 => 'APPOINTMENT_RESCHEDULED',
            2 => 'APPOINTMENT_CANCELLED',
            3 => 'APPOINTMENT_ENTERED_RESOLUTION',
            4 => 'CONFIRMATION_REQUESTED',
            5 => 'CONFIRMATION_DELIVERY_CONFIRMED',
            6 => 'CONFIRMATION_OPENED',
            7 => 'CONFIRMED',
            8 => 'ATTENTION_STARTED',
            9 => 'ATTENDED',
            10 => 'NO_SHOW_RECORDED',
            11 => 'NO_SHOW_CORRECTED',
            12 => 'APPOINTMENT_ADVANCE_OFFERED',
            13 => 'APPOINTMENT_ADVANCE_DELIVERED',
            14 => 'APPOINTMENT_ADVANCE_OPENED',
            15 => 'APPOINTMENT_ADVANCE_ACCEPTED',
            16 => 'APPOINTMENT_ADVANCE_UNAVAILABLE',
            17 => 'APPOINTMENT_ADVANCE_EXPIRED',
            18 => 'SYSTEM_SLOT_RELEASED',
            19 => 'MODALITY_CHANGED',
            20 => 'OVERBOOK_CREATED',
        ],
        'metrics' => [
            0 => [
                'code' => 'CLOSED_ELIGIBLE',
                'kind' => 'count',
                'description' => 'Turnos cerrados elegibles (atendidos + no-show atribuibles)',
            ],
            1 => [
                'code' => 'ATTENDED',
                'kind' => 'count',
                'description' => 'Turnos atendidos',
            ],
            2 => [
                'code' => 'NO_SHOW_ATTRIBUTABLE',
                'kind' => 'count',
                'description' => 'No-show atribuibles a la persona',
            ],
            3 => [
                'code' => 'NO_SHOW_RATE',
                'kind' => 'rate',
                'numerator' => 'NO_SHOW_ATTRIBUTABLE',
                'denominator' => 'CLOSED_ELIGIBLE',
                'description' => 'Tasa de no-show atribuible',
            ],
            4 => [
                'code' => 'CANCEL_PATIENT',
                'kind' => 'count',
                'description' => 'Cancelaciones iniciadas por paciente o representante',
            ],
            5 => [
                'code' => 'CANCEL_EARLY',
                'kind' => 'count',
                'description' => 'Cancelaciones paciente fuera de la ventana tardía',
            ],
            6 => [
                'code' => 'CANCEL_LATE',
                'kind' => 'count',
                'description' => 'Cancelaciones paciente dentro de la ventana tardía',
            ],
            7 => [
                'code' => 'RESCHEDULED',
                'kind' => 'count',
                'description' => 'Reprogramaciones con vínculo anterior/nuevo',
            ],
            8 => [
                'code' => 'CONFIRMATION_REQUESTED',
                'kind' => 'count',
                'description' => 'Solicitudes de confirmación',
            ],
            9 => [
                'code' => 'CONFIRMATION_DELIVERED',
                'kind' => 'count',
                'description' => 'Confirmaciones con entrega del canal',
            ],
            10 => [
                'code' => 'CONFIRMATION_RESPONDED',
                'kind' => 'count',
                'description' => 'Confirmaciones respondidas por la persona',
            ],
            11 => [
                'code' => 'CONFIRMATION_RATE',
                'kind' => 'rate',
                'numerator' => 'CONFIRMATION_RESPONDED',
                'denominator' => 'CONFIRMATION_DELIVERED',
                'description' => 'Tasa de confirmación sobre entregas',
            ],
            12 => [
                'code' => 'ATTENDED_AFTER_CONFIRM',
                'kind' => 'count',
                'description' => 'Atendidos entre turnos previamente confirmados',
            ],
            13 => [
                'code' => 'CONFIRMED_CLOSED',
                'kind' => 'count',
                'description' => 'Turnos confirmados que cerraron (atendido o no-show)',
            ],
            14 => [
                'code' => 'ATTENDED_AFTER_CONFIRM_RATE',
                'kind' => 'rate',
                'numerator' => 'ATTENDED_AFTER_CONFIRM',
                'denominator' => 'CONFIRMED_CLOSED',
                'description' => 'Asistencia posterior a confirmación',
            ],
            15 => [
                'code' => 'COVERAGE_NATIVE',
                'kind' => 'count',
                'description' => 'Outcomes cerrados con atribución nativa',
            ],
            16 => [
                'code' => 'COVERAGE_RATE',
                'kind' => 'rate',
                'numerator' => 'COVERAGE_NATIVE',
                'denominator' => 'CLOSED_ELIGIBLE',
                'description' => 'Cobertura de outcomes nativos sobre cerrados elegibles',
            ],
        ],
    ];
    }
}
