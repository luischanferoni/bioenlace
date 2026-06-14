<?php

namespace common\components\Domain\Clinical\Dto;

use common\models\Clinical\DiagnosticReport;

final class DiagnosticReportDto
{
    public string $resourceType = 'DiagnosticReport';

    public int $id;

    public int $subjectPersonaId;

    public ?int $encounterId;

    public string $sourceSystem;

    public string $externalId;

    public string $status;

    public ?string $display;

    public ?string $issuedAt;

    public ?string $conclusion;

    public static function fromModel(DiagnosticReport $model): self
    {
        $dto = new self();
        $dto->id = (int) $model->id;
        $dto->subjectPersonaId = (int) $model->subject_persona_id;
        $dto->encounterId = $model->encounter_id !== null ? (int) $model->encounter_id : null;
        $dto->sourceSystem = (string) $model->source_system;
        $dto->externalId = (string) $model->external_id;
        $dto->status = (string) $model->status;
        $dto->display = $model->display !== null ? (string) $model->display : null;
        $dto->issuedAt = $model->issued_at !== null ? (string) $model->issued_at : null;
        $dto->conclusion = $model->conclusion !== null ? (string) $model->conclusion : null;

        return $dto;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'resourceType' => $this->resourceType,
            'id' => $this->id,
            'subjectPersonaId' => $this->subjectPersonaId,
            'encounterId' => $this->encounterId,
            'sourceSystem' => $this->sourceSystem,
            'externalId' => $this->externalId,
            'status' => $this->status,
            'display' => $this->display,
            'issuedAt' => $this->issuedAt,
            'conclusion' => $this->conclusion,
        ];
    }
}
