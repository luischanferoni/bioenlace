<?php

namespace common\components\Domain\Clinical\Dto;

use common\models\Clinical\Procedure;
use common\models\Clinical\ProcedureOdontologyExt;

final class ProcedureDto
{
    public string $resourceType = 'Procedure';

    public int $id;

    public int $encounterId;

    public string $status;

    public ?string $code;

    public ?string $display;

    /** @var array<string, mixed>|null */
    public ?array $odontology = null;

    public static function fromModel(Procedure $procedure, bool $withOdontology = true): self
    {
        $dto = new self();
        $dto->id = (int) $procedure->id;
        $dto->encounterId = (int) $procedure->encounter_id;
        $dto->status = (string) $procedure->status;
        $dto->code = $procedure->code !== null ? (string) $procedure->code : null;
        $dto->display = $procedure->display !== null ? (string) $procedure->display : null;

        if ($withOdontology) {
            $ext = $procedure->odontologyExt ?? ProcedureOdontologyExt::findOne(['procedure_id' => $procedure->id]);
            if ($ext !== null) {
                $dto->odontology = [
                    'toothNumber' => $ext->tooth_number,
                    'surfaces' => $ext->surfaces,
                    'timeQualifier' => $ext->time_qualifier,
                ];
            }
        }

        return $dto;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $out = [
            'resourceType' => $this->resourceType,
            'id' => $this->id,
            'encounterId' => $this->encounterId,
            'status' => $this->status,
            'code' => $this->code,
            'display' => $this->display,
        ];
        if ($this->odontology !== null) {
            $out['odontology'] = $this->odontology;
        }

        return $out;
    }
}
