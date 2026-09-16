<?php

namespace common\models\Clinical;

use common\models\Clinical\Input\OftalmologiaEstudioInput;

/**
 * Tipología de captura — estudios oftalmológicos. Sin tabla legacy.
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
        $input = OftalmologiaEstudioInput::fromExtractedRow($row);

        return [
            'missing_fields' => $input->missingFieldsForCompleteness(),
            'label' => $input->rowLabel(),
            'input' => $input,
        ];
    }
}
