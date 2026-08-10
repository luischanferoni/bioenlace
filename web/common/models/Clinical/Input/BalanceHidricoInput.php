<?php

namespace common\models\Clinical\Input;

use common\models\ConsultaBalanceHidrico;
use yii\base\Model;

/**
 * Balance hídrico en captura IMP.
 * Integridad: Tipo Registro (Ingreso|Egreso) + Cantidad; Fecha opcional en dictado.
 *
 * Cada fila es un movimiento (ingreso o egreso), no el balance neto del día.
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
            $model->ingestFreeText($row);

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
        $model->cantidad = self::firstNonEmptyString($row, [self::FIELD_CANTIDAD, 'cantidad', 'volume', 'volumen']);

        $blob = trim(implode(' ', array_filter([
            self::firstNonEmptyString($row, ['texto', 'text', 'label', 'display', 'descripcion', 'descripción']),
            is_string($row[self::FIELD_TIPO] ?? null) ? (string) $row[self::FIELD_TIPO] : null,
            is_string($row[self::FIELD_CANTIDAD] ?? null) ? (string) $row[self::FIELD_CANTIDAD] : null,
        ])));
        if ($blob !== '') {
            $model->ingestFreeText($blob);
        }
        $model->normalizeTipoRegistro();
        $model->normalizeCantidad();

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
                'message' => 'El tipo de registro debe ser Ingreso o Egreso.',
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

    /**
     * @return list<array{id: string, field: string, options: list<array{value: mixed, label: string}>, allow_custom: bool}>
     */
    public function buildIssues(string $category, int $index): array
    {
        $issues = [];
        foreach ($this->missingFieldsForCompleteness() as $field) {
            if ($field === self::FIELD_TIPO) {
                $issues[] = \common\components\Domain\Clinical\Capture\ClinicalCaptureIssueFactory::make(
                    $category,
                    $index,
                    $field,
                    [
                        ['value' => ConsultaBalanceHidrico::TREG_INGRESO, 'label' => 'Ingreso (fluidos que entran)'],
                        ['value' => ConsultaBalanceHidrico::TREG_EGRESO, 'label' => 'Egreso (fluidos que salen)'],
                    ],
                    false
                );
                continue;
            }
            if ($field === self::FIELD_CANTIDAD) {
                $issues[] = \common\components\Domain\Clinical\Capture\ClinicalCaptureIssueFactory::make(
                    $category,
                    $index,
                    $field,
                    [
                        ['value' => '500', 'label' => '500 ml'],
                        ['value' => '1000', 'label' => '1000 ml'],
                        ['value' => '1500', 'label' => '1500 ml'],
                        ['value' => '2000', 'label' => '2000 ml'],
                        ['value' => '2200', 'label' => '2200 ml'],
                    ],
                    true
                );
            }
        }

        return $issues;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public static function applyResolutionToRow(array $row, string $field, mixed $value): array
    {
        $row[$field] = is_string($value) ? trim($value) : $value;
        if ($field === self::FIELD_TIPO) {
            $tmp = new self();
            $tmp->tipoRegistro = is_string($value) ? $value : (string) $value;
            $tmp->normalizeTipoRegistro();
            if ($tmp->tipoRegistro !== null && $tmp->tipoRegistro !== '') {
                $row[self::FIELD_TIPO] = $tmp->tipoRegistro;
                $row['tipo_registro'] = $tmp->tipoRegistro;
            }
        }
        if ($field === self::FIELD_CANTIDAD) {
            $tmp = new self();
            $tmp->cantidad = is_string($value) || is_numeric($value) ? (string) $value : '';
            $tmp->normalizeCantidad();
            if ($tmp->cantidad !== null && $tmp->cantidad !== '') {
                $row[self::FIELD_CANTIDAD] = $tmp->cantidad;
                $row['cantidad'] = $tmp->cantidad;
            }
        }

        return $row;
    }

    public function rowLabel(): string
    {
        $tipo = trim((string) ($this->tipoRegistro ?? ''));
        $cant = trim((string) ($this->cantidad ?? ''));
        if ($tipo !== '' && $cant !== '') {
            return $tipo . ' ' . $cant . (ctype_digit($cant) || is_numeric($cant) ? ' ml' : '');
        }
        if ($cant !== '') {
            return 'balance / volumen ' . $cant . (is_numeric($cant) ? ' ml' : '');
        }

        return $tipo !== '' ? $tipo : 'ítem';
    }

    /**
     * Completa tipo/cantidad desde texto libre (fila string o label/texto de la extracción).
     */
    private function ingestFreeText(string $text): void
    {
        $raw = trim($text);
        if ($raw === '') {
            return;
        }
        $lower = mb_strtolower($raw, 'UTF-8');

        $hasIngresoWord = (bool) preg_match('/\bingresos?\b/u', $lower);
        $hasEgresoWord = (bool) preg_match('/\begresos?\b/u', $lower);
        $isBalanceNeto = (bool) preg_match('/\bbalance\b/u', $lower) && !$hasIngresoWord && !$hasEgresoWord;

        if ($isBalanceNeto) {
            // El neto del día no es un tipo de fila del modelo.
            $this->tipoRegistro = null;
        } elseif ($this->tipoRegistro === null || $this->tipoRegistro === '') {
            if ($hasIngresoWord || str_starts_with($lower, 'ingreso')) {
                $this->tipoRegistro = ConsultaBalanceHidrico::TREG_INGRESO;
            } elseif ($hasEgresoWord || str_starts_with($lower, 'egreso')) {
                $this->tipoRegistro = ConsultaBalanceHidrico::TREG_EGRESO;
            }
        }

        if ($this->cantidad === null || $this->cantidad === '') {
            if (preg_match('/([+-]?\d+(?:[.,]\d+)?)\s*(ml|cc)?/ui', $raw, $m)) {
                $this->cantidad = str_replace(',', '.', $m[1]);
            }
        }

        $this->normalizeTipoRegistro();
        $this->normalizeCantidad();
    }

    private function normalizeTipoRegistro(): void
    {
        $raw = mb_strtolower(trim((string) ($this->tipoRegistro ?? '')), 'UTF-8');
        if ($raw === '') {
            $this->tipoRegistro = null;

            return;
        }
        if ($raw === 'ingreso' || str_starts_with($raw, 'ingres')) {
            $this->tipoRegistro = ConsultaBalanceHidrico::TREG_INGRESO;

            return;
        }
        if ($raw === 'egreso' || str_starts_with($raw, 'egres')) {
            $this->tipoRegistro = ConsultaBalanceHidrico::TREG_EGRESO;

            return;
        }
        if (str_contains($raw, 'balance') || str_contains($raw, 'neto')) {
            $this->tipoRegistro = null;
        }
    }

    private function normalizeCantidad(): void
    {
        $raw = trim((string) ($this->cantidad ?? ''));
        if ($raw === '') {
            $this->cantidad = null;

            return;
        }
        if (preg_match('/([+-]?\d+(?:[.,]\d+)?)/u', $raw, $m)) {
            $this->cantidad = str_replace(',', '.', $m[1]);
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
