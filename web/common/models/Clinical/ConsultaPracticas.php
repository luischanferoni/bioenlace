<?php

namespace common\models\Clinical;

use common\models\Clinical\Input\PracticaInput;

/**
 * Tipología de captura de prácticas (legacy name `ConsultaPracticas`).
 * Persistencia: ServiceRequest / Procedure. Sin tabla `consultas_practicas`.
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
        $input = PracticaInput::fromExtractedRow($row);
        $label = trim((string) ($input->practica ?? ''));

        return [
            'missing_fields' => $input->missingFieldsForCompleteness(),
            'label' => $label !== '' ? $label : 'ítem',
            'input' => $input,
        ];
    }
}
