<?php

namespace common\models\Clinical\Input;

use yii\base\Model;

/**
 * Encounter.reason (chief complaint) en captura → Condition rol CC.
 */
final class EncounterReasonInput extends Model
{
    public const FIELD_MOTIVO = 'Motivo';
    public const FIELD_CODIGO = 'Codigo';

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
        if (is_string($row)) {
            $model->motivo = trim($row);

            return $model;
        }
        if (!is_array($row)) {
            return $model;
        }
        $model->motivo = self::firstNonEmpty($row, [
            self::FIELD_MOTIVO,
            'texto',
            'termino',
            'descripcion',
            'label',
            'display',
            'motivo',
        ]);
        $model->codigo = self::firstNonEmpty($row, [self::FIELD_CODIGO, 'codigo', 'code']);

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
        $row[$field] = $value;
        if ($field === self::FIELD_MOTIVO) {
            $row['texto'] = $value;
            $row['display'] = $value;
        }
        if ($field === self::FIELD_CODIGO) {
            $row['codigo'] = $value;
        }

        return $row;
    }

    /**
     * @param array<string, mixed> $row
     * @param list<string> $keys
     */
    private static function firstNonEmpty(array $row, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (!array_key_exists($key, $row)) {
                continue;
            }
            $v = trim((string) $row[$key]);
            if ($v !== '') {
                return $v;
            }
        }

        return null;
    }
}
