<?php

namespace common\components\Domain\Integrations\Scheduling\Mapper;

use common\models\Scheduling\Turno;

/**
 * FHIR Appointment.status ↔ estado interno turnos.
 */
final class FhirAppointmentStatusMapper
{
    /**
     * @return array{estado: string, fhir_status: string}
     */
    public static function mapToTurnoEstado(string $fhirStatus): array
    {
        $fhir = strtolower(trim($fhirStatus));

        return match ($fhir) {
            'booked', 'pending', 'proposed', 'waitlist' => [
                'estado' => Turno::ESTADO_PENDIENTE,
                'fhir_status' => $fhir,
            ],
            'arrived', 'checked-in' => [
                'estado' => Turno::ESTADO_EN_ATENCION,
                'fhir_status' => $fhir,
            ],
            'fulfilled' => [
                'estado' => Turno::ESTADO_ATENDIDO,
                'fhir_status' => $fhir,
            ],
            'cancelled', 'entered-in-error' => [
                'estado' => Turno::ESTADO_CANCELADO,
                'fhir_status' => $fhir,
            ],
            'noshow' => [
                'estado' => Turno::ESTADO_SIN_ATENDER,
                'fhir_status' => $fhir,
            ],
            default => [
                'estado' => Turno::ESTADO_PENDIENTE,
                'fhir_status' => $fhir !== '' ? $fhir : 'unknown',
            ],
        };
    }
}
