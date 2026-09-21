<?php

namespace common\models\Clinical;

use common\components\Domain\Clinical\Capture\Domain\RowContract\OftalmologiaEstudioRowContract;
use common\models\Clinical\Input\OftalmologiaEstudioInput;

/**
 * Tipología de captura — estudios oftalmológicos. Sin tabla legacy.
 * Contrato Domain: {@see OftalmologiaEstudioRowContract}.
 */
final class ConsultaPracticasOftalmologiaEstudios extends \yii\base\Model
{
    /**
     * @return list<string>
     */
    public function requeridosPrompt(): array
    {
        return OftalmologiaEstudioInput::promptFieldNames();
    }

    /**
     * @param array<string, mixed>|string $row
     * @return array{missing_fields: list<string>, label: string, input: OftalmologiaEstudioInput}
     */
    public static function completenessForExtractedRow($row): array
    {
        $assessment = OftalmologiaEstudioRowContract::assess($row);

        return [
            'missing_fields' => $assessment->missingFields(),
            'label' => $assessment->label(),
            'input' => OftalmologiaEstudioInput::fromExtractedRow($row),
        ];
    }
}
