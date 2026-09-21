<?php

namespace common\models\Clinical;

use common\components\Domain\Clinical\Capture\Domain\Policy\BalanceHidricoRowContract;
use common\models\Clinical\Input\BalanceHidricoInput;

/**
 * Tipología de captura de balance hídrico.
 * Contrato Domain: {@see BalanceHidricoRowContract}.
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
        $assessment = BalanceHidricoRowContract::assess($row);

        return [
            'missing_fields' => $assessment->missingFields(),
            'label' => $assessment->label(),
            'input' => BalanceHidricoInput::fromExtractedRow($row),
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public static function applyResolutionToRow(array $row, string $field, mixed $value): array
    {
        return BalanceHidricoRowContract::applyResolution($row, $field, $value);
    }
}
