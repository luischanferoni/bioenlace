<?php

namespace common\components\Domain\Clinical\Dto;

use common\models\Clinical\CarePlanActivity;

final class CarePlanActivityDto
{
    public string $resourceType = 'CarePlanActivity';

    public int $id;

    public string $kind;

    public string $resourceTypeRef;

    public int $resourceId;

    public string $status;

    public int $sortOrder;

    public static function fromModel(CarePlanActivity $a): self
    {
        $dto = new self();
        $dto->id = (int) $a->id;
        $dto->kind = (string) $a->kind;
        $dto->resourceTypeRef = (string) $a->resource_type;
        $dto->resourceId = (int) $a->resource_id;
        $dto->status = (string) $a->status;
        $dto->sortOrder = (int) $a->sort_order;

        return $dto;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'resourceType' => $this->resourceType,
            'id' => $this->id,
            'kind' => $this->kind,
            'resourceTypeRef' => $this->resourceTypeRef,
            'resourceId' => $this->resourceId,
            'status' => $this->status,
            'sortOrder' => $this->sortOrder,
        ];
    }
}
