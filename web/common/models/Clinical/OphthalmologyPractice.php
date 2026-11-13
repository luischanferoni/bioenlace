<?php

namespace common\models\Clinical;

use common\components\Domain\Clinical\Capture\Domain\RowContract\OftalmologiaPracticaRowContract;
use common\models\Clinical\Input\OftalmologiaPracticaInput;

/**
 * Capture model for ophthalmology practices.
 * Contrato Domain: {@see OftalmologiaPracticaRowContract}.
 */
final class OphthalmologyPractice extends \yii\base\Model
{
    /**
     * @return list<string>
     */
    public function requeridosPrompt(): array
    {
        return OftalmologiaPracticaInput::promptFieldNames();
    }

    /**
     * @param array<string, mixed>|string $row
     * @return array{missing_fields: list<string>, label: string, input: OftalmologiaPracticaInput}
     */
    public static function completenessForExtractedRow($row): array
    {
        $assessment = OftalmologiaPracticaRowContract::assess($row);

        return [
            'missing_fields' => $assessment->missingFields(),
            'label' => $assessment->label(),
            'input' => OftalmologiaPracticaInput::fromExtractedRow($row),
        ];
    }
}
