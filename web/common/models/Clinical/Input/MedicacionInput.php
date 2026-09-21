<?php

namespace common\models\Clinical\Input;

use common\components\Domain\Clinical\Capture\Domain\Catalog\MedicacionCatalog;
use common\components\Domain\Clinical\Capture\Domain\RowContract\MedicacionRowContract;
use yii\base\Model;

/**
 * Contrato de entrada de medicación.
 * Completitud/resoluciones: {@see MedicacionRowContract}.
 */
final class MedicacionInput extends Model
{
    public const TYPE_MENTIONED = MedicacionRowContract::TYPE_MENTIONED;
    public const TYPE_ORDERED = MedicacionRowContract::TYPE_ORDERED;

    public const FIELD_NOMBRE = MedicacionRowContract::FIELD_NOMBRE;
    public const FIELD_TIPO = MedicacionRowContract::FIELD_TIPO;
    public const FIELD_CANTIDAD = MedicacionRowContract::FIELD_CANTIDAD;
    public const FIELD_VIA = MedicacionRowContract::FIELD_VIA;
    public const FIELD_FRECUENCIA = MedicacionRowContract::FIELD_FRECUENCIA;
    public const FIELD_TIPO_FRECUENCIA = MedicacionRowContract::FIELD_TIPO_FRECUENCIA;
    public const FIELD_DURACION = MedicacionRowContract::FIELD_DURACION;
    public const FIELD_TIPO_DURACION = MedicacionRowContract::FIELD_TIPO_DURACION;

    /** @var string|null */
    public $nombre;

    /** @var string|null */
    public $tipo;

    /** @var string|null */
    public $cantidad;

    /** @var string|null */
    public $via;

    /** @var string|null */
    public $frecuencia;

    /** @var string|null */
    public $tipoFrecuencia;

    /** @var string|null */
    public $duracion;

    /** @var string|null */
    public $tipoDuracion;

    /**
     * @return list<string>
     */
    public static function promptFieldNames(): array
    {
        return [
            self::FIELD_NOMBRE,
            self::FIELD_TIPO,
            self::FIELD_CANTIDAD,
            self::FIELD_VIA,
            self::FIELD_FRECUENCIA,
            self::FIELD_TIPO_FRECUENCIA,
            self::FIELD_DURACION,
            self::FIELD_TIPO_DURACION,
        ];
    }

    /**
     * @return list<string>
     */
    public static function typeValues(): array
    {
        return MedicacionRowContract::typeValues();
    }

    /**
     * @param array<string, mixed>|string $row
     */
    public static function fromExtractedRow($row): self
    {
        $p = MedicacionRowContract::parse($row);
        $model = new self();
        $model->nombre = $p['nombre'] !== '' ? $p['nombre'] : null;
        $model->tipo = $p['tipo'];
        $model->cantidad = $p['cantidad'];
        $model->via = $p['via'];
        $model->frecuencia = $p['frecuencia'];
        $model->tipoFrecuencia = $p['tipoFrecuencia'];
        $model->duracion = $p['duracion'];
        $model->tipoDuracion = $p['tipoDuracion'];

        return $model;
    }

    public function rules(): array
    {
        $freqTypes = array_keys(MedicacionCatalog::FRECUENCIAS);
        $durTypes = array_keys(MedicacionCatalog::DURANTES);

        return [
            [['nombre'], 'trim'],
            [['nombre'], 'required', 'message' => 'Falta el nombre del medicamento.'],
            [['tipo'], 'required', 'message' => 'Falta el tipo de medicación (mentioned|ordered).'],
            [['tipo'], 'in', 'range' => self::typeValues()],
            [
                ['cantidad', 'frecuencia'],
                'required',
                'when' => static fn (self $m) => $m->tipo === self::TYPE_ORDERED,
                'message' => 'Campo requerido para medicación indicada.',
            ],
            [
                ['tipoFrecuencia'],
                'required',
                'when' => static fn (self $m) => $m->tipo === self::TYPE_ORDERED
                    && trim((string) ($m->frecuencia ?? '')) !== '',
            ],
            [
                ['tipoDuracion'],
                'required',
                'when' => static fn (self $m) => trim((string) ($m->duracion ?? '')) !== '',
            ],
            [['tipoFrecuencia'], 'in', 'range' => $freqTypes, 'skipOnEmpty' => true],
            [['tipoDuracion'], 'in', 'range' => $durTypes, 'skipOnEmpty' => true],
            [['cantidad', 'via', 'frecuencia', 'duracion'], 'string'],
        ];
    }

    /**
     * @return list<string>
     */
    public function missingFieldsForCompleteness(): array
    {
        return MedicacionRowContract::missingFields([
            'nombre' => (string) ($this->nombre ?? ''),
            'tipo' => $this->tipo,
            'cantidad' => $this->cantidad,
            'via' => $this->via,
            'frecuencia' => $this->frecuencia,
            'tipoFrecuencia' => $this->tipoFrecuencia,
            'duracion' => $this->duracion,
            'tipoDuracion' => $this->tipoDuracion,
        ]);
    }

    /**
     * @return list<array{id: string, field: string, options: list<array{value: mixed, label: string}>, allow_custom: bool}>
     */
    public function buildIssues(string $category, int $index): array
    {
        return MedicacionRowContract::assess($this->toExtractedRow(), $category, $index)->issues();
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public static function applyResolutionToRow(array $row, string $field, mixed $value): array
    {
        return MedicacionRowContract::applyResolution($row, $field, $value);
    }

    /**
     * @return list<array{value: mixed, label: string}>
     */
    public static function optionsForField(string $field): array
    {
        return MedicacionRowContract::optionsForField($field);
    }

    /**
     * @return array<string, mixed>
     */
    public function toExtractedRow(): array
    {
        return [
            self::FIELD_NOMBRE => (string) ($this->nombre ?? ''),
            self::FIELD_TIPO => (string) ($this->tipo ?? self::TYPE_MENTIONED),
            self::FIELD_CANTIDAD => $this->cantidad,
            self::FIELD_VIA => $this->via,
            self::FIELD_FRECUENCIA => $this->frecuencia,
            self::FIELD_TIPO_FRECUENCIA => $this->tipoFrecuencia,
            self::FIELD_DURACION => $this->duracion,
            self::FIELD_TIPO_DURACION => $this->tipoDuracion,
        ];
    }
}
