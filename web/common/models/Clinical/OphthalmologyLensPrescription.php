<?php

namespace common\models\Clinical;

use common\components\Domain\Clinical\Capture\Domain\RowContract\OftalmologiaRecetaRowContract;
use common\models\Clinical\Input\OftalmologiaRecetaInput;

/**
 * Capture model for lens prescriptions, persisted as VisionPrescription.
 * Contrato Domain: {@see OftalmologiaRecetaRowContract}.
 */
final class OphthalmologyLensPrescription extends \yii\base\Model
{
    /**
     * @return list<string>
     */
    public function requeridosPrompt(): array
    {
        return OftalmologiaRecetaInput::promptFieldNames();
    }

    /**
     * @param array<string, mixed>|string $row
     * @return array{missing_fields: list<string>, label: string, input: OftalmologiaRecetaInput}
     */
    public static function completenessForExtractedRow($row): array
    {
        $assessment = OftalmologiaRecetaRowContract::assess($row);

        return [
            'missing_fields' => $assessment->missingFields(),
            'label' => $assessment->label(),
            'input' => OftalmologiaRecetaInput::fromExtractedRow($row),
        ];
    }
}
