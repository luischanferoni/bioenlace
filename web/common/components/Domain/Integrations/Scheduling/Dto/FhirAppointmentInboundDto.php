<?php

namespace common\components\Domain\Integrations\Scheduling\Dto;

/**
 * Cita FHIR normalizada para sync inbound.
 */
final class FhirAppointmentInboundDto
{
    public function __construct(
        public readonly string $externalId,
        public readonly string $sourceSystem,
        public readonly string $fhirStatus,
        public readonly ?string $startAt,
        public readonly ?string $endAt,
        public readonly string $scheduleId,
        public readonly ?int $idPersona,
        public readonly string $patientCuil = '',
        public readonly string $patientDni = '',
        public readonly string $versionId = '',
        public readonly ?string $lastUpdated = null,
    ) {
    }
}
