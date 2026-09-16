<?php

namespace common\models\Clinical;

use common\models\Clinical\Input\BalanceHidricoInput;

/**
 * Tipología de captura de balance hídrico (legacy name `ConsultaBalanceHidrico`).
 * Persistencia: Observation FHIR. Sin tabla `consultas_balancehidrico`.
 */
final class ConsultaBalanceHidrico extends \yii\base\Model
{
    /**
     * @return list<string>
     */
    public function requeridosPrompt(): array
    {
        return BalanceHidricoInput::promptFieldNames();
    }

    /**
     * @param array<string, mixed>|string $row
     * @return array{missing_fields: list<string>, label: string, input: BalanceHidricoInput}
     */
    public static function completenessForExtractedRow($row): array
    {
        $input = BalanceHidricoInput::fromExtractedRow($row);

        return [
            'missing_fields' => $input->missingFieldsForCompleteness(),
            'label' => $input->rowLabel(),
            'input' => $input,
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public static function applyResolutionToRow(array $row, string $field, mixed $value): array
    {
        return BalanceHidricoInput::applyResolutionToRow($row, $field, $value);
    }
}
