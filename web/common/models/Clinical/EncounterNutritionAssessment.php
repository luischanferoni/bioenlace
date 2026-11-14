<?php

namespace common\models\Clinical;

use common\components\Domain\Clinical\Capture\Domain\RowContract\NutricionEvaluacionRowContract;
use common\models\Clinical\Input\NutricionEvaluacionInput;

/**
 * Capture model for a nutrition assessment.
 * Contrato Domain: {@see NutricionEvaluacionRowContract}.
 */
final class EncounterNutritionAssessment extends \yii\base\Model
{
    /** @var list<string> */
    public const PROMPT_FIELDS = NutricionEvaluacionRowContract::PROMPT_FIELDS;

    /**
     * @return list<string>
     */
    public function requeridosPrompt(): array
    {
        return self::PROMPT_FIELDS;
    }

    /**
     * @param array<string, mixed>|string $row
     * @return array{missing_fields: list<string>, label: string, input: NutricionEvaluacionInput}
     */
    public static function completenessForExtractedRow($row): array
    {
        $assessment = NutricionEvaluacionRowContract::assess($row);

        return [
            'missing_fields' => $assessment->missingFields(),
            'label' => $assessment->label(),
            'input' => NutricionEvaluacionInput::fromExtractedRow($row),
        ];
    }
}
