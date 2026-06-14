<?php

namespace common\components\Domain\Clinical\Dto;

use common\models\Clinical\ServiceRequest;

final class ServiceRequestDto
{
    public string $resourceType = 'ServiceRequest';

    public int $id;

    public int $encounterId;

    public int $subjectPersonaId;

    public ?int $carePlanId;

    public string $status;

    public string $intent;

    public string $category;

    public ?string $code;

    public ?string $display;

    public static function fromModel(ServiceRequest $sr): self
    {
        $dto = new self();
        $dto->id = (int) $sr->id;
        $dto->encounterId = (int) $sr->encounter_id;
        $dto->subjectPersonaId = (int) $sr->subject_persona_id;
        $dto->carePlanId = $sr->care_plan_id !== null ? (int) $sr->care_plan_id : null;
        $dto->status = (string) $sr->status;
        $dto->intent = (string) $sr->intent;
        $dto->category = (string) $sr->category;
        $dto->code = $sr->code !== null ? (string) $sr->code : null;
        $dto->display = $sr->display !== null ? (string) $sr->display : null;

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
            'category' => $this->category,
            'code' => $this->code,
            'display' => $this->display,
        ];
    }
}
