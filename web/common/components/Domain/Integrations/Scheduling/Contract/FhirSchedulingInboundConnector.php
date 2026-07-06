<?php

namespace common\components\Domain\Integrations\Scheduling\Contract;

/**
 * Pull de Schedule / Appointment desde servidor FHIR (HAPI NIS).
 */
interface FhirSchedulingInboundConnector
{
    public function getConnectorKey(): string;

    /**
     * @param array<string, scalar|null> $params parámetros de búsqueda FHIR
     * @return array<string, mixed> Bundle
     */
    public function searchAppointments(array $params = []): array;

    /**
     * @param array<string, scalar|null> $params
     * @return array<string, mixed> Bundle
     */
    public function searchSchedules(array $params = []): array;

    /**
     * @return array<string, mixed> recurso o Bundle con includes
     */
    public function readSchedule(string $id, array $includes = []): array;

    /**
     * @return array<string, mixed> recurso o Bundle con includes
     */
    public function readAppointment(string $id, array $includes = []): array;

    /**
     * @return array<string, mixed>
     */
    public function readResource(string $resourceType, string $id): array;

    /**
     * Actualiza Appointment.status en el servidor FHIR (PUT recurso completo).
     *
     * @return array<string, mixed> recurso Appointment actualizado
     */
    public function updateAppointmentStatus(string $appointmentId, string $fhirStatus): array;
}
