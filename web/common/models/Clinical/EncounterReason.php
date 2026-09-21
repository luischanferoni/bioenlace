<?php

namespace common\models\Clinical;

use common\components\Domain\Clinical\Capture\Domain\RowContract\ReasonRowContract;
use common\models\Clinical\Input\EncounterReasonInput;

/**
 * Tipología de captura — Encounter.reason / chief complaint (categoría `EncounterReason`).
 * Persistencia: {@see \common\components\Domain\Clinical\Encounter\Application\Service\EncounterReasonService}
 * → Condition con rol CC.
 *
 * Contrato Domain: {@see ReasonRowContract}.
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
        $assessment = ReasonRowContract::assess($row);
        $input = EncounterReasonInput::fromExtractedRow($row);

        return [
            'missing_fields' => $assessment->missingFields(),
            'label' => $assessment->label(),
            'input' => $input,
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public static function applyResolutionToRow(array $row, string $field, mixed $value): array
    {
        return ReasonRowContract::applyResolution($row, $field, $value);
    }
}
