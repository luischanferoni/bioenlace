<?php

namespace common\models\Clinical\Input;

use common\models\ConsultaBalanceHidrico;
use yii\base\Model;

/**
 * Balance hídrico en captura IMP.
 * Integridad: Tipo Registro + Cantidad; Fecha opcional en dictado.
 */
final class BalanceHidricoInput extends Model
{
    public const FIELD_FECHA = 'Fecha';
    public const FIELD_TIPO = 'Tipo Registro';
    public const FIELD_CANTIDAD = 'Cantidad';

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
        $model = new self();
        if (is_string($row)) {
            $model->tipoRegistro = trim($row);

            return $model;
        }
        if (!is_array($row)) {
            return $model;
        }

        $model->fecha = self::firstNonEmptyString($row, [self::FIELD_FECHA, 'fecha', 'date']);
        $model->tipoRegistro = self::firstNonEmptyString($row, [
            self::FIELD_TIPO,
            'tipo_registro',
            'tipoRegistro',
            'tipo',
        ]);
        $model->cantidad = self::firstNonEmptyString($row, [self::FIELD_CANTIDAD, 'cantidad', 'volume']);
        $model->normalizeTipoRegistro();

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
                'range' => [ConsultaBalanceHidrico::TREG_INGRESO, ConsultaBalanceHidrico::TREG_EGRESO],
                'skipOnEmpty' => true,
            ],
        ];
    }

    /**
     * @return list<string>
     */
    public function missingFieldsForCompleteness(): array
    {
        if ($this->validate()) {
            return [];
        }
        $missing = [];
        if ($this->hasErrors('tipoRegistro')) {
            $missing[] = self::FIELD_TIPO;
        }
        if ($this->hasErrors('cantidad')) {
            $missing[] = self::FIELD_CANTIDAD;
        }

        return $missing;
    }

    public function rowLabel(): string
    {
        $tipo = trim((string) ($this->tipoRegistro ?? ''));
        $cant = trim((string) ($this->cantidad ?? ''));
        if ($tipo !== '' && $cant !== '') {
            return $tipo . ' ' . $cant;
        }

        return $tipo !== '' ? $tipo : ($cant !== '' ? $cant : 'ítem');
    }

    private function normalizeTipoRegistro(): void
    {
        $raw = mb_strtolower(trim((string) ($this->tipoRegistro ?? '')), 'UTF-8');
        if ($raw === '') {
            return;
        }
        if (str_contains($raw, 'ingres')) {
            $this->tipoRegistro = ConsultaBalanceHidrico::TREG_INGRESO;
        } elseif (str_contains($raw, 'egres')) {
            $this->tipoRegistro = ConsultaBalanceHidrico::TREG_EGRESO;
        }
    }

    /**
     * @param array<string, mixed> $row
     * @param list<string> $keys
     */
    private static function firstNonEmptyString(array $row, array $keys): ?string
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
