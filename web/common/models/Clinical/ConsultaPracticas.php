<?php

namespace common\models\Clinical;

use common\components\Domain\Clinical\Capture\Domain\Policy\PracticaRowContract;
use common\models\Clinical\Input\PracticaInput;

/**
 * Tipología de captura de prácticas (legacy name `ConsultaPracticas`).
 * Persistencia: ServiceRequest / Procedure. Sin tabla `consultas_practicas`.
 * Contrato Domain: {@see PracticaRowContract}.
 */
final class ConsultaPracticas extends \yii\base\Model
{
    /**
     * @return list<string>
     */
    public function requeridosPrompt(): array
    {
        return PracticaInput::promptFieldNames();
    }

    /**
     * @param array<string, mixed>|string $row
     * @return array{missing_fields: list<string>, label: string, input: PracticaInput}
     */
    public static function completenessForExtractedRow($row): array
    {
        $assessment = PracticaRowContract::assess($row);

        return [
            'missing_fields' => $assessment->missingFields(),
            'label' => $assessment->label(),
            'input' => PracticaInput::fromExtractedRow($row),
        ];
    }
}
