<?php

namespace common\components\Domain\Clinical\Dto;

use common\models\Clinical\MedicationRequest;

final class MedicationRequestDto
{
    public string $resourceType = 'MedicationRequest';

    public int $id;

    public int $encounterId;

    public int $subjectPersonaId;

    public ?int $carePlanId;

    public string $status;

    public string $intent;

    public ?string $medicationCode;

    public ?string $medicationDisplay;

    public ?string $dosageText;

    public static function fromModel(MedicationRequest $mr): self
    {
        $dto = new self();
        $dto->id = (int) $mr->id;
        $dto->encounterId = (int) $mr->encounter_id;
        $dto->subjectPersonaId = (int) $mr->subject_persona_id;
        $dto->carePlanId = $mr->care_plan_id !== null ? (int) $mr->care_plan_id : null;
        $dto->status = (string) $mr->status;
        $dto->intent = (string) $mr->intent;
        $dto->medicationCode = $mr->medication_code !== null ? (string) $mr->medication_code : null;
        $dto->medicationDisplay = $mr->medication_display !== null ? (string) $mr->medication_display : null;
        $dto->dosageText = $mr->dosage_text !== null ? (string) $mr->dosage_text : null;

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
            'carePlanId' => $this->carePlanId,
            'status' => $this->status,
            'intent' => $this->intent,
            'medicationCode' => $this->medicationCode,
            'medicationDisplay' => $this->medicationDisplay,
            'dosageText' => $this->dosageText,
        ];
    }
}
