<?php

namespace common\models\Clinical;

use common\models\Clinical\Input\RegimenInput;

/**
 * Tipología de captura de régimen/dieta (legacy name `ConsultaRegimen`).
 * Persistencia: NutritionOrder FHIR. Sin tabla `consultas_regimen`.
 */
final class ConsultaRegimen extends \yii\base\Model
{
    /**
     * @return list<string>
     */
    public function requeridosPrompt(): array
    {
        return RegimenInput::promptFieldNames();
    }

    /**
     * @param array<string, mixed>|string $row
     * @return array{missing_fields: list<string>, label: string, input: RegimenInput}
     */
    public static function completenessForExtractedRow($row): array
    {
        $input = RegimenInput::fromExtractedRow($row);

        return [
            'missing_fields' => $input->missingFieldsForCompleteness(),
            'label' => $input->rowLabel(),
            'input' => $input,
        ];
    }
}
