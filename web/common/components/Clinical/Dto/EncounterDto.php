<?php

namespace common\components\Clinical\Dto;

use common\models\Clinical\Encounter;

/**
 * Respuesta API — recurso Encounter (FHIR).
 */
final class EncounterDto
{
    public string $resourceType = 'Encounter';

    public int $id;

    public int $subjectPersonaId;

    public ?int $appointmentId;

    public string $encounterClass;

    public string $status;

    public ?int $serviceId;

    public ?int $efectorId;

    public static function fromModel(Encounter $e): self
    {
        $dto = new self();
        $dto->id = (int) $e->id;
        $dto->subjectPersonaId = (int) $e->subject_persona_id;
        $dto->appointmentId = $e->appointment_id !== null ? (int) $e->appointment_id : null;
        $dto->encounterClass = (string) $e->encounter_class;
        $dto->status = (string) $e->status;
        $dto->serviceId = $e->service_id !== null ? (int) $e->service_id : null;
        $dto->efectorId = $e->efector_id !== null ? (int) $e->efector_id : null;

        return $dto;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'resourceType' => $this->resourceType,
            'id' => $this->id,
            'subjectPersonaId' => $this->subjectPersonaId,
            'appointmentId' => $this->appointmentId,
            'encounterClass' => $this->encounterClass,
            'status' => $this->status,
            'serviceId' => $this->serviceId,
            'efectorId' => $this->efectorId,
            'encounter_id' => $this->id,
            'id_consulta' => $this->id,
        ];
    }
}
