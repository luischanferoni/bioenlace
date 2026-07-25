<?php

namespace common\models;

use common\models\Clinical\Input\IndicacionInput;

/**
 * Tipología de extracción/prompt para indicaciones clínicas (no es práctica realizada).
 * Contrato de integridad: {@see IndicacionInput}.
 * Persistencia: ServiceRequest / care plan (mismo canal que prácticas).
 */
class ConsultaIndicaciones extends \yii\base\Model
{
    /**
     * Esquema NL para prompts de extracción (incluye opcionales condicionales).
     *
     * @return list<string>
     */
    public function requeridosPrompt()
    {
        return IndicacionInput::promptFieldNames();
    }

    /**
     * @param array<string, mixed>|string $row
     * @return array{missing_fields: list<string>, label: string, input: IndicacionInput}
     */
    public static function completenessForExtractedRow($row): array
    {
        $input = IndicacionInput::fromExtractedRow($row);
        $label = trim((string) ($input->indicacion ?? ''));

        return [
            'missing_fields' => $input->missingFieldsForCompleteness(),
            'label' => $label !== '' ? $label : 'ítem',
            'input' => $input,
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public static function applyResolutionToRow(array $row, string $field, mixed $value): array
    {
        return IndicacionInput::applyResolutionToRow($row, $field, $value);
    }
}
