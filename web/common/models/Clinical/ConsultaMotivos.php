<?php

namespace common\models\Clinical;

use common\models\Clinical\Input\MotivoInput;

/**
 * Tipología de captura — motivos de consulta (categoría `ConsultaMotivos`).
 * Persistencia: {@see \common\components\Domain\Clinical\Encounter\Service\EncounterReasonService}
 * → Condition con rol CC. Sin columna `reason_text`.
 */
final class ConsultaMotivos extends \yii\base\Model
{
    /**
     * @return list<string>
     */
    public function requeridosPrompt(): array
    {
        return MotivoInput::promptFieldNames();
    }

    /**
     * @param array<string, mixed>|string $row
     * @return array{missing_fields: list<string>, label: string, input: MotivoInput}
     */
    public static function completenessForExtractedRow($row): array
    {
        $input = MotivoInput::fromExtractedRow($row);

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
        return MotivoInput::applyResolutionToRow($row, $field, $value);
    }
}
