<?php

namespace common\components\Clinical\Dto;

use common\models\Clinical\VisionPrescription;

final class VisionPrescriptionDto
{
    public string $resourceType = 'VisionPrescription';

    public int $id;

    public int $encounterId;

    public string $status;

    /** @var array<string, mixed>|null */
    public ?array $lensSpec;

    public static function fromModel(VisionPrescription $vp): self
    {
        $dto = new self();
        $dto->id = (int) $vp->id;
        $dto->encounterId = (int) $vp->encounter_id;
        $dto->status = (string) $vp->status;
        if ($vp->lens_spec_json !== null && $vp->lens_spec_json !== '') {
            $decoded = json_decode($vp->lens_spec_json, true);
            $dto->lensSpec = is_array($decoded) ? $decoded : null;
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
            'status' => $this->status,
            'lensSpec' => $this->lensSpec,
        ];
    }
}
