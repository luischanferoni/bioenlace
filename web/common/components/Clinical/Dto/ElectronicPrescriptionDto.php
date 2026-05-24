<?php

namespace common\components\Clinical\Dto;

use common\models\Clinical\ElectronicPrescription;

final class ElectronicPrescriptionDto
{
    public string $resourceType = 'ElectronicPrescription';

    public int $id;
    public int $encounterId;
    public int $subjectPersonaId;
    public ?int $idProfesionalEfectorServicio;
    public string $status;
    public ?string $prescriptionNumber;
    public ?string $diagnosisCode;
    public ?string $diagnosisCodeSystem;
    public ?string $diagnosisDisplay;
    public ?string $validFrom;
    public ?string $validUntil;
    public ?string $issuedAt;
    public ?string $cancelledAt;
    public ?string $cancellationReason;
    public ?string $notes;

    /** @var list<ElectronicPrescriptionItemDto> */
    public array $items = [];

    public static function fromModel(ElectronicPrescription $rx, bool $withItems = true): self
    {
        $dto = new self();
        $dto->id = (int) $rx->id;
        $dto->encounterId = (int) $rx->encounter_id;
        $dto->subjectPersonaId = (int) $rx->subject_persona_id;
        $dto->idProfesionalEfectorServicio = $rx->id_profesional_efector_servicio !== null
            ? (int) $rx->id_profesional_efector_servicio
            : null;
        $dto->status = (string) $rx->status;
        $dto->prescriptionNumber = $rx->prescription_number !== null ? (string) $rx->prescription_number : null;
        $dto->diagnosisCode = $rx->diagnosis_code !== null ? (string) $rx->diagnosis_code : null;
        $dto->diagnosisCodeSystem = $rx->diagnosis_code_system !== null ? (string) $rx->diagnosis_code_system : null;
        $dto->diagnosisDisplay = $rx->diagnosis_display !== null ? (string) $rx->diagnosis_display : null;
        $dto->validFrom = $rx->valid_from !== null ? (string) $rx->valid_from : null;
        $dto->validUntil = $rx->valid_until !== null ? (string) $rx->valid_until : null;
        $dto->issuedAt = $rx->issued_at !== null ? (string) $rx->issued_at : null;
        $dto->cancelledAt = $rx->cancelled_at !== null ? (string) $rx->cancelled_at : null;
        $dto->cancellationReason = $rx->cancellation_reason !== null ? (string) $rx->cancellation_reason : null;
        $dto->notes = $rx->notes !== null ? (string) $rx->notes : null;

        if ($withItems) {
            foreach ($rx->items as $item) {
                $dto->items[] = ElectronicPrescriptionItemDto::fromModel($item);
            }
        }

        return $dto;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'resourceType' => $this->resourceType,
            'id' => $this->id,
            'encounterId' => $this->encounterId,
            'subjectPersonaId' => $this->subjectPersonaId,
            'idProfesionalEfectorServicio' => $this->idProfesionalEfectorServicio,
            'status' => $this->status,
            'prescriptionNumber' => $this->prescriptionNumber,
            'diagnosisCode' => $this->diagnosisCode,
            'diagnosisCodeSystem' => $this->diagnosisCodeSystem,
            'diagnosisDisplay' => $this->diagnosisDisplay,
            'validFrom' => $this->validFrom,
            'validUntil' => $this->validUntil,
            'issuedAt' => $this->issuedAt,
            'cancelledAt' => $this->cancelledAt,
            'cancellationReason' => $this->cancellationReason,
            'notes' => $this->notes,
            'items' => array_map(static fn (ElectronicPrescriptionItemDto $i) => $i->toArray(), $this->items),
        ];
    }
}
