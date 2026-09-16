<?php

namespace common\models\Clinical;

use common\models\Clinical\Input\OdontologiaItemInput;

/**
 * Tipología de captura odontológica — estados. Sin tabla legacy.
 */
final class ConsultaOdontologiaEstados extends \yii\base\Model
{
    /**
     * @return list<string>
     */
    public function requeridosPrompt(): array
    {
        return OdontologiaItemInput::promptFieldNames();
    }

    /**
     * @param array<string, mixed>|string $row
     * @return array{missing_fields: list<string>, label: string, input: OdontologiaItemInput}
     */
    public static function completenessForExtractedRow($row): array
    {
        $input = OdontologiaItemInput::fromExtractedRow($row);

        return [
            'missing_fields' => $input->missingFieldsForCompleteness(),
            'label' => $input->rowLabel(),
            'input' => $input,
        ];
    }
}
