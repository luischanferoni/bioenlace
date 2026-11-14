<?php

namespace common\models\Clinical;

use common\components\Domain\Clinical\Capture\Domain\RowContract\ObstetriciaControlRowContract;
use common\models\Clinical\Input\ObstetriciaControlInput;

/**
 * Capture model for an obstetric prenatal control.
 * Contrato Domain: {@see ObstetriciaControlRowContract}.
 */
final class EncounterObstetricControl extends \yii\base\Model
{
    /** @var list<string> */
    public const PROMPT_FIELDS = ObstetriciaControlRowContract::PROMPT_FIELDS;

    /**
     * @return list<string>
     */
    public function requeridosPrompt(): array
    {
        return self::PROMPT_FIELDS;
    }

    /**
     * @param array<string, mixed>|string $row
     * @return array{missing_fields: list<string>, label: string, input: ObstetriciaControlInput}
     */
    public static function completenessForExtractedRow($row): array
    {
        $assessment = ObstetriciaControlRowContract::assess($row);

        return [
            'missing_fields' => $assessment->missingFields(),
            'label' => $assessment->label(),
            'input' => ObstetriciaControlInput::fromExtractedRow($row),
        ];
    }
}
