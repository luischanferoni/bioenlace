<?php

namespace common\models\Clinical;

use common\components\Domain\Clinical\Capture\Domain\Policy\RegimenRowContract;
use common\models\Clinical\Input\RegimenInput;

/**
 * Tipología de captura de régimen/dieta (legacy name `ConsultaRegimen`).
 * Persistencia: NutritionOrder FHIR. Sin tabla `consultas_regimen`.
 * Contrato Domain: {@see RegimenRowContract}.
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
        $assessment = RegimenRowContract::assess($row);

        return [
            'missing_fields' => $assessment->missingFields(),
            'label' => $assessment->label(),
            'input' => RegimenInput::fromExtractedRow($row),
        ];
    }
}
