<?php

namespace common\models\Clinical;

use common\components\Domain\Clinical\Capture\Domain\RowContract\SaludMentalSesionRowContract;
use common\models\Clinical\Input\SaludMentalSesionInput;

/**
 * Capture model for a mental-health or psychology session.
 * Contrato Domain: {@see SaludMentalSesionRowContract}.
 */
final class EncounterMentalHealthSession extends \yii\base\Model
{
    /**
     * @return list<string>
     */
    public function requeridosPrompt(): array
    {
        return SaludMentalSesionRowContract::promptFields();
    }

    /**
     * @param array<string, mixed>|string $row
     * @return array{missing_fields: list<string>, label: string, input: SaludMentalSesionInput}
     */
    public static function completenessForExtractedRow($row): array
    {
        $assessment = SaludMentalSesionRowContract::assess($row);

        return [
            'missing_fields' => $assessment->missingFields(),
            'label' => $assessment->label(),
            'input' => SaludMentalSesionInput::fromExtractedRow($row),
        ];
    }
}
