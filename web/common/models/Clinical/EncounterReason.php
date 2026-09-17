<?php

namespace common\models\Clinical;

use common\models\Clinical\Input\EncounterReasonInput;

/**
 * Tipología de captura — Encounter.reason / chief complaint (categoría `EncounterReason`).
 * Persistencia: {@see \common\components\Domain\Clinical\Encounter\Service\EncounterReasonService}
 * → Condition con rol CC.
 */
final class EncounterReason extends \yii\base\Model
{
    /**
     * @return list<string>
     */
    public function requeridosPrompt(): array
    {
        return EncounterReasonInput::promptFieldNames();
    }

    /**
     * @param array<string, mixed>|string $row
     * @return array{missing_fields: list<string>, label: string, input: EncounterReasonInput}
     */
    public static function completenessForExtractedRow($row): array
    {
        $input = EncounterReasonInput::fromExtractedRow($row);

        return [
            'missing_fields' => $input->missingFieldsForCompleteness(),
            'label' => $input->rowLabel(),
            'input' => $input,
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public static function applyResolutionToRow(array $row, string $field, mixed $value): array
    {
        return EncounterReasonInput::applyResolutionToRow($row, $field, $value);
    }
}
