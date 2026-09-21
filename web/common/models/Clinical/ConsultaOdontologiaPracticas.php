<?php

namespace common\models\Clinical;

use common\components\Domain\Clinical\Capture\Domain\Policy\OdontologiaItemRowContract;
use common\models\Clinical\Input\OdontologiaItemInput;

/**
 * Tipología de captura odontológica — prácticas. Sin tabla legacy.
 * Contrato Domain: {@see OdontologiaItemRowContract}.
 */
final class ConsultaOdontologiaPracticas extends \yii\base\Model
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
        $assessment = OdontologiaItemRowContract::assess($row);

        return [
            'missing_fields' => $assessment->missingFields(),
            'label' => $assessment->label(),
            'input' => OdontologiaItemInput::fromExtractedRow($row),
        ];
    }
}
