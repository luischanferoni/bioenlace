<?php

namespace common\models\Clinical;

use common\components\Domain\Clinical\Capture\Domain\RowContract\IndicacionRowContract;
use common\models\Clinical\Input\IndicacionInput;

/**
 * Capture model for clinical instructions.
 * Contrato Domain: {@see IndicacionRowContract}.
 * Persistencia: ServiceRequest / care plan.
 */
final class EncounterInstruction extends \yii\base\Model
{
    /**
     * @return list<string>
     */
    public function requeridosPrompt()
    {
        return IndicacionInput::promptFieldNames();
    }

    /**
     * @param array<string, mixed>|string $row
     * @return array{missing_fields: list<string>, label: string, input: IndicacionInput}
     */
    public static function completenessForExtractedRow($row): array
    {
        $assessment = IndicacionRowContract::assess($row);

        return [
            'missing_fields' => $assessment->missingFields(),
            'label' => $assessment->label(),
            'input' => IndicacionInput::fromExtractedRow($row),
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public static function applyResolutionToRow(array $row, string $field, mixed $value): array
    {
        return IndicacionRowContract::applyResolution($row, $field, $value);
    }
}
