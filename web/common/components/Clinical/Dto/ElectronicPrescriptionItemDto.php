<?php

namespace common\components\Clinical\Dto;

use common\models\Clinical\ElectronicPrescriptionItem;

final class ElectronicPrescriptionItemDto
{
    public int $id;
    public int $lineNumber;
    public ?int $medicationRequestId;
    public ?string $medicationCode;
    public ?string $medicationCodeSystem;
    public ?string $medicationDisplay;
    public ?string $quantityText;
    public ?string $dosageText;

    public static function fromModel(ElectronicPrescriptionItem $item): self
    {
        $dto = new self();
        $dto->id = (int) $item->id;
        $dto->lineNumber = (int) $item->line_number;
        $dto->medicationRequestId = $item->medication_request_id !== null ? (int) $item->medication_request_id : null;
        $dto->medicationCode = $item->medication_code !== null ? (string) $item->medication_code : null;
        $dto->medicationCodeSystem = $item->medication_code_system !== null ? (string) $item->medication_code_system : null;
        $dto->medicationDisplay = $item->medication_display !== null ? (string) $item->medication_display : null;
        $dto->quantityText = $item->quantity_text !== null ? (string) $item->quantity_text : null;
        $dto->dosageText = $item->dosage_text !== null ? (string) $item->dosage_text : null;

        return $dto;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'lineNumber' => $this->lineNumber,
            'medicationRequestId' => $this->medicationRequestId,
            'medicationCode' => $this->medicationCode,
            'medicationCodeSystem' => $this->medicationCodeSystem,
            'medicationDisplay' => $this->medicationDisplay,
            'quantityText' => $this->quantityText,
            'dosageText' => $this->dosageText,
        ];
    }
}
