<?php

namespace common\models\Clinical;

use common\components\Domain\Clinical\Capture\Domain\RowContract\KinesiologiaSesionRowContract;
use common\models\Clinical\Input\KinesiologiaSesionInput;

/**
 * Capture model for a kinesiology session.
 * Contrato Domain: {@see KinesiologiaSesionRowContract}.
 */
final class EncounterKinesiologySession extends \yii\base\Model
{
    /** @var list<string> */
    public const PROMPT_FIELDS = KinesiologiaSesionRowContract::PROMPT_FIELDS;

    /**
     * @return list<string>
     */
    public function requeridosPrompt(): array
    {
        return self::PROMPT_FIELDS;
    }

    /**
     * @param array<string, mixed>|string $row
     * @return array{missing_fields: list<string>, label: string, input: KinesiologiaSesionInput}
     */
    public static function completenessForExtractedRow($row): array
    {
        $assessment = KinesiologiaSesionRowContract::assess($row);

        return [
            'missing_fields' => $assessment->missingFields(),
            'label' => $assessment->label(),
            'input' => KinesiologiaSesionInput::fromExtractedRow($row),
        ];
    }
}
