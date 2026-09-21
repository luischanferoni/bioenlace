<?php

namespace common\models\Clinical\Input;

use common\components\Domain\Clinical\Capture\Domain\RowContract\EncounterReasonRowContract;
use yii\base\Model;

/**
 * Encounter.reason (chief complaint) en captura → Condition rol CC.
 * Completitud/resoluciones: {@see EncounterReasonRowContract}.
 */
final class EncounterReasonInput extends Model
{
    public const FIELD_MOTIVO = EncounterReasonRowContract::FIELD_MOTIVO;
    public const FIELD_CODIGO = EncounterReasonRowContract::FIELD_CODIGO;

    /** @var string|null */
    public $motivo;

    /** @var string|null */
    public $codigo;

    /**
     * @return list<string>
     */
    public static function promptFieldNames(): array
    {
        return [self::FIELD_MOTIVO, self::FIELD_CODIGO];
    }

    /**
     * @param array<string, mixed>|string $row
     */
    public static function fromExtractedRow($row): self
    {
        $model = new self();
        $model->motivo = EncounterReasonRowContract::extractMotivo($row) ?: null;
        $model->codigo = EncounterReasonRowContract::extractCodigo($row) ?: null;

        return $model;
    }

    public function rules(): array
    {
        return [
            [['motivo', 'codigo'], 'string'],
            [['motivo'], 'required', 'message' => 'Indique el motivo de consulta.'],
        ];
    }

    /**
     * @return list<string>
     */
    public function missingFieldsForCompleteness(): array
    {
        return trim((string) ($this->motivo ?? '')) === '' ? [self::FIELD_MOTIVO] : [];
    }

    public function rowLabel(): string
    {
        $m = trim((string) ($this->motivo ?? ''));

        return $m !== '' ? $m : 'motivo';
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public static function applyResolutionToRow(array $row, string $field, mixed $value): array
    {
        return EncounterReasonRowContract::applyResolution($row, $field, $value);
    }
}
