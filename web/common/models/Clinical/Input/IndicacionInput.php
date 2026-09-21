<?php

namespace common\models\Clinical\Input;

use common\components\Domain\Clinical\Capture\Domain\RowContract\IndicacionRowContract;
use yii\base\Model;

/**
 * Contrato de entrada de una indicación clínica.
 * Completitud/resoluciones: {@see IndicacionRowContract}.
 */
final class IndicacionInput extends Model
{
    public const TYPE_COUNSELING = IndicacionRowContract::TYPE_COUNSELING;
    public const TYPE_CONDITIONAL = IndicacionRowContract::TYPE_CONDITIONAL;
    public const TYPE_FOLLOW_UP = IndicacionRowContract::TYPE_FOLLOW_UP;

    public const FIELD_INDICACION = IndicacionRowContract::FIELD_INDICACION;
    public const FIELD_TIPO = IndicacionRowContract::FIELD_TIPO;
    public const FIELD_PLAZO_DIAS = IndicacionRowContract::FIELD_PLAZO_DIAS;

    /** @var string|null */
    public $indicacion;

    /** @var string|null */
    public $tipo;

    /** @var int|null */
    public $plazoDias;

    /**
     * @return list<string>
     */
    public static function promptFieldNames(): array
    {
        return [self::FIELD_INDICACION, self::FIELD_TIPO, self::FIELD_PLAZO_DIAS];
    }

    /**
     * @return list<string>
     */
    public static function typeValues(): array
    {
        return IndicacionRowContract::typeValues();
    }

    /**
     * @param array<string, mixed>|string $row
     */
    public static function fromExtractedRow($row): self
    {
        $p = IndicacionRowContract::parse($row);
        $model = new self();
        $model->indicacion = $p['indicacion'] !== '' ? $p['indicacion'] : null;
        $model->tipo = $p['tipo'];
        $model->plazoDias = $p['plazoDias'];

        return $model;
    }

    public function rules(): array
    {
        return [
            [['indicacion'], 'trim'],
            [['indicacion'], 'required', 'message' => 'Falta el texto de la indicación.'],
            [['tipo'], 'required', 'message' => 'Falta el tipo de indicación.'],
            [['tipo'], 'in', 'range' => self::typeValues()],
            [['plazoDias'], 'integer', 'min' => 1],
            [
                ['plazoDias'],
                'required',
                'when' => static fn (self $m) => $m->tipo === self::TYPE_FOLLOW_UP,
                'message' => 'Falta el plazo en días para el control.',
            ],
        ];
    }

    /**
     * @return list<string>
     */
    public function missingFieldsForCompleteness(): array
    {
        return IndicacionRowContract::missingFields([
            'indicacion' => (string) ($this->indicacion ?? ''),
            'tipo' => $this->tipo,
            'plazoDias' => $this->plazoDias,
        ]);
    }

    /**
     * @return list<array{id: string, field: string, options: list<array{value: mixed, label: string}>, allow_custom: bool}>
     */
    public function buildIssues(string $category, int $index): array
    {
        return IndicacionRowContract::assess(
            $this->toExtractedRow(),
            $category,
            $index
        )->issues();
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public static function applyResolutionToRow(array $row, string $field, mixed $value): array
    {
        return IndicacionRowContract::applyResolution($row, $field, $value);
    }

    public function applyResolution(string $field, mixed $value): void
    {
        $row = IndicacionRowContract::applyResolution($this->toExtractedRow(), $field, $value);
        $this->indicacion = isset($row[self::FIELD_INDICACION]) ? (string) $row[self::FIELD_INDICACION] : $this->indicacion;
        $this->tipo = isset($row[self::FIELD_TIPO]) ? (string) $row[self::FIELD_TIPO] : $this->tipo;
        if (array_key_exists(self::FIELD_PLAZO_DIAS, $row)) {
            $this->plazoDias = is_numeric($row[self::FIELD_PLAZO_DIAS]) ? (int) $row[self::FIELD_PLAZO_DIAS] : $this->plazoDias;
        }
    }

    public function categoryForServiceRequest(): string
    {
        return $this->tipo === self::TYPE_FOLLOW_UP ? 'follow-up' : 'counseling';
    }

    /**
     * @return array<string, mixed>
     */
    public function toExtractedRow(): array
    {
        return [
            self::FIELD_INDICACION => (string) ($this->indicacion ?? ''),
            self::FIELD_TIPO => (string) ($this->tipo ?? self::TYPE_COUNSELING),
            self::FIELD_PLAZO_DIAS => $this->plazoDias,
        ];
    }
}
