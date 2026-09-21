<?php

namespace common\models\Clinical\Input;

use common\components\Domain\Clinical\Capture\Domain\RowContract\BalanceHidricoRowContract;
use common\components\Domain\Clinical\Inpatient\Domain\Model\InpatientFluidBalanceRow;
use yii\base\Model;

/**
 * Balance hídrico en captura IMP.
 * Completitud/resoluciones: {@see BalanceHidricoRowContract}.
 */
final class BalanceHidricoInput extends Model
{
    public const FIELD_FECHA = BalanceHidricoRowContract::FIELD_FECHA;
    public const FIELD_TIPO = BalanceHidricoRowContract::FIELD_TIPO;
    public const FIELD_CANTIDAD = BalanceHidricoRowContract::FIELD_CANTIDAD;

    /** @var string|null */
    public $fecha;

    /** @var string|null */
    public $tipoRegistro;

    /** @var string|null */
    public $cantidad;

    /**
     * @return list<string>
     */
    public static function promptFieldNames(): array
    {
        return [self::FIELD_FECHA, self::FIELD_TIPO, self::FIELD_CANTIDAD];
    }

    /**
     * @param array<string, mixed>|string $row
     */
    public static function fromExtractedRow($row): self
    {
        $p = BalanceHidricoRowContract::parse($row);
        $model = new self();
        $model->fecha = $p['fecha'];
        $model->tipoRegistro = $p['tipoRegistro'];
        $model->cantidad = $p['cantidad'];

        return $model;
    }

    public function rules(): array
    {
        return [
            [['fecha', 'tipoRegistro', 'cantidad'], 'string'],
            [['tipoRegistro'], 'required', 'message' => 'Falta el tipo de registro (Ingreso/Egreso).'],
            [['cantidad'], 'required', 'message' => 'Falta la cantidad.'],
            [
                ['tipoRegistro'],
                'in',
                'range' => [InpatientFluidBalanceRow::TREG_INGRESO, InpatientFluidBalanceRow::TREG_EGRESO],
                'skipOnEmpty' => true,
                'message' => 'El tipo de registro debe ser Ingreso o Egreso.',
            ],
        ];
    }

    /**
     * @return list<string>
     */
    public function missingFieldsForCompleteness(): array
    {
        return BalanceHidricoRowContract::assess([
            self::FIELD_FECHA => $this->fecha,
            self::FIELD_TIPO => $this->tipoRegistro,
            self::FIELD_CANTIDAD => $this->cantidad,
        ])->missingFields();
    }

    /**
     * @return list<array{id: string, field: string, options: list<array{value: mixed, label: string}>, allow_custom: bool}>
     */
    public function buildIssues(string $category, int $index): array
    {
        return BalanceHidricoRowContract::assess([
            self::FIELD_FECHA => $this->fecha,
            self::FIELD_TIPO => $this->tipoRegistro,
            self::FIELD_CANTIDAD => $this->cantidad,
        ], $category, $index)->issues();
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public static function applyResolutionToRow(array $row, string $field, mixed $value): array
    {
        return BalanceHidricoRowContract::applyResolution($row, $field, $value);
    }

    public function rowLabel(): string
    {
        return BalanceHidricoRowContract::label([
            'fecha' => $this->fecha,
            'tipoRegistro' => $this->tipoRegistro,
            'cantidad' => $this->cantidad,
        ]);
    }
}
