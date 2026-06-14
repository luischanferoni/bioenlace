<?php

namespace common\components\Domain\Clinical\Dto;

use common\models\Clinical\Observation;

final class ObservationDto
{
    public string $resourceType = 'Observation';

    public int $id;

    public int $encounterId;

    public string $status;

    public string $category;

    public string $code;

    public ?string $display;

    public ?string $valueString;

    public ?string $eye;

    public static function fromModel(Observation $obs): self
    {
        $dto = new self();
        $dto->id = (int) $obs->id;
        $dto->encounterId = (int) $obs->encounter_id;
        $dto->status = (string) $obs->status;
        $dto->category = (string) $obs->category;
        $dto->code = (string) $obs->code;
        $dto->display = $obs->value_string !== null && $obs->value_string !== ''
            ? (string) $obs->value_string
            : null;
        $dto->valueString = $obs->value_string !== null ? (string) $obs->value_string : null;

        $json = $obs->value_json !== null ? json_decode($obs->value_json, true) : null;
        if (is_array($json) && isset($json['eye'])) {
            $dto->eye = (string) $json['eye'];
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
            'category' => $this->category,
            'code' => $this->code,
            'display' => $this->display,
            'valueString' => $this->valueString,
            'eye' => $this->eye,
        ];
    }
}
