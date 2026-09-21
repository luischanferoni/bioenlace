<?php

namespace common\components\Domain\Clinical\Capture\Domain\Policy;

use common\components\Domain\Clinical\Capture\Domain\Model\ClinicalCaptureIssueFactory;
use common\components\Domain\Clinical\Capture\Domain\Model\ClinicalCaptureRowCompleteness;

/**
 * Contrato Domain: balance hídrico (`ConsultaBalanceHidrico`).
 * Tipo registro alineado con filas inpatient (Ingreso|Egreso).
 */
final class BalanceHidricoRowContract
{
    public const MODELO = 'ConsultaBalanceHidrico';

    public const TREG_INGRESO = 'Ingreso';
    public const TREG_EGRESO = 'Egreso';

    public const FIELD_FECHA = 'Fecha';
    public const FIELD_TIPO = 'Tipo Registro';
    public const FIELD_CANTIDAD = 'Cantidad';

    public static function matchesModelo(string $modelo): bool
    {
        return ExtractedRowFields::matchesModelo($modelo, self::MODELO);
    }

    /**
     * @param array<string, mixed>|string $row
     * @return array{fecha: string|null, tipoRegistro: string|null, cantidad: string|null}
     */
    public static function parse($row): array
    {
        $fecha = null;
        $tipoRegistro = null;
        $cantidad = null;

        if (($s = ExtractedRowFields::stringRow($row)) !== null) {
            return self::afterIngest(['fecha' => null, 'tipoRegistro' => null, 'cantidad' => null], $s);
        }
        if (!is_array($row)) {
            return ['fecha' => null, 'tipoRegistro' => null, 'cantidad' => null];
        }

        $fecha = ExtractedRowFields::firstNonEmpty($row, [self::FIELD_FECHA, 'fecha', 'date']);
        $tipoRegistro = ExtractedRowFields::firstNonEmpty($row, [
            self::FIELD_TIPO,
            'tipo_registro',
            'tipoRegistro',
            'tipo',
        ]);
        $cantidad = ExtractedRowFields::firstNonEmpty($row, [
            self::FIELD_CANTIDAD,
            'cantidad',
            'volume',
            'volumen',
        ]);

        $parsed = [
            'fecha' => $fecha,
            'tipoRegistro' => $tipoRegistro,
            'cantidad' => $cantidad,
        ];
        $blob = trim(implode(' ', array_filter([
            ExtractedRowFields::firstNonEmpty($row, ['texto', 'text', 'label', 'display', 'descripcion', 'descripción']),
            is_string($row[self::FIELD_TIPO] ?? null) ? (string) $row[self::FIELD_TIPO] : null,
            is_string($row[self::FIELD_CANTIDAD] ?? null) ? (string) $row[self::FIELD_CANTIDAD] : null,
        ])));
        if ($blob !== '') {
            $parsed = self::afterIngest($parsed, $blob);
        } else {
            $parsed['tipoRegistro'] = self::normalizeTipoRegistro($parsed['tipoRegistro']);
            $parsed['cantidad'] = self::normalizeCantidad($parsed['cantidad']);
        }

        return $parsed;
    }

    /**
     * @param array<string, mixed>|string $row
     */
    public static function assess($row, string $categoryTitle = '', int $index = 0): ClinicalCaptureRowCompleteness
    {
        $p = self::parse($row);
        $missing = [];
        $tipo = (string) ($p['tipoRegistro'] ?? '');
        $cant = (string) ($p['cantidad'] ?? '');
        if ($tipo === '' || !in_array($tipo, [self::TREG_INGRESO, self::TREG_EGRESO], true)) {
            $missing[] = self::FIELD_TIPO;
        }
        if ($cant === '') {
            $missing[] = self::FIELD_CANTIDAD;
        }
        $issues = self::buildIssues($missing, $categoryTitle, $index);

        return new ClinicalCaptureRowCompleteness($missing, self::label($p), $issues);
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public static function applyResolution(array $row, string $field, mixed $value): array
    {
        $row[$field] = is_string($value) ? trim($value) : $value;
        if ($field === self::FIELD_TIPO) {
            $norm = self::normalizeTipoRegistro(is_string($value) ? $value : (string) $value);
            if ($norm !== null && $norm !== '') {
                $row[self::FIELD_TIPO] = $norm;
                $row['tipo_registro'] = $norm;
            }
        }
        if ($field === self::FIELD_CANTIDAD) {
            $norm = self::normalizeCantidad(is_string($value) || is_numeric($value) ? (string) $value : '');
            if ($norm !== null && $norm !== '') {
                $row[self::FIELD_CANTIDAD] = $norm;
                $row['cantidad'] = $norm;
            }
        }

        return $row;
    }

    /**
     * @param array{fecha: string|null, tipoRegistro: string|null, cantidad: string|null} $p
     */
    public static function label(array $p): string
    {
        $tipo = trim((string) ($p['tipoRegistro'] ?? ''));
        $cant = trim((string) ($p['cantidad'] ?? ''));
        if ($tipo !== '' && $cant !== '') {
            return $tipo . ' ' . $cant . (ctype_digit($cant) || is_numeric($cant) ? ' ml' : '');
        }
        if ($cant !== '') {
            return 'balance / volumen ' . $cant . (is_numeric($cant) ? ' ml' : '');
        }

        return $tipo !== '' ? $tipo : 'ítem';
    }

    /**
     * @param array{fecha: string|null, tipoRegistro: string|null, cantidad: string|null} $p
     * @return array{fecha: string|null, tipoRegistro: string|null, cantidad: string|null}
     */
    private static function afterIngest(array $p, string $text): array
    {
        $raw = trim($text);
        if ($raw === '') {
            return $p;
        }
        $lower = mb_strtolower($raw, 'UTF-8');
        $hasIngresoWord = (bool) preg_match('/\bingresos?\b/u', $lower);
        $hasEgresoWord = (bool) preg_match('/\begresos?\b/u', $lower);
        $isBalanceNeto = (bool) preg_match('/\bbalance\b/u', $lower) && !$hasIngresoWord && !$hasEgresoWord;

        if ($isBalanceNeto) {
            $p['tipoRegistro'] = null;
        } elseif ($p['tipoRegistro'] === null || $p['tipoRegistro'] === '') {
            if ($hasIngresoWord || str_starts_with($lower, 'ingreso')) {
                $p['tipoRegistro'] = self::TREG_INGRESO;
            } elseif ($hasEgresoWord || str_starts_with($lower, 'egreso')) {
                $p['tipoRegistro'] = self::TREG_EGRESO;
            }
        }

        if ($p['cantidad'] === null || $p['cantidad'] === '') {
            if (preg_match('/([+-]?\d+(?:[.,]\d+)?)\s*(ml|cc)?/ui', $raw, $m)) {
                $p['cantidad'] = str_replace(',', '.', $m[1]);
            }
        }

        $p['tipoRegistro'] = self::normalizeTipoRegistro($p['tipoRegistro']);
        $p['cantidad'] = self::normalizeCantidad($p['cantidad']);

        return $p;
    }

    private static function normalizeTipoRegistro(?string $tipoRegistro): ?string
    {
        $raw = mb_strtolower(trim((string) $tipoRegistro), 'UTF-8');
        if ($raw === '') {
            return null;
        }
        if ($raw === 'ingreso' || str_starts_with($raw, 'ingres')) {
            return self::TREG_INGRESO;
        }
        if ($raw === 'egreso' || str_starts_with($raw, 'egres')) {
            return self::TREG_EGRESO;
        }
        if (str_contains($raw, 'balance') || str_contains($raw, 'neto')) {
            return null;
        }

        return $tipoRegistro;
    }

    private static function normalizeCantidad(?string $cantidad): ?string
    {
        $raw = trim((string) $cantidad);
        if ($raw === '') {
            return null;
        }
        if (preg_match('/([+-]?\d+(?:[.,]\d+)?)/u', $raw, $m)) {
            return str_replace(',', '.', $m[1]);
        }

        return $cantidad;
    }

    /**
     * @param list<string> $missing
     * @return list<array{id: string, field: string, options: list<array{value: mixed, label: string}>, allow_custom: bool}>
     */
    private static function buildIssues(array $missing, string $category, int $index): array
    {
        $issues = [];
        foreach ($missing as $field) {
            if ($field === self::FIELD_TIPO) {
                $issues[] = ClinicalCaptureIssueFactory::make($category, $index, $field, [
                    ['value' => self::TREG_INGRESO, 'label' => 'Ingreso (fluidos que entran)'],
                    ['value' => self::TREG_EGRESO, 'label' => 'Egreso (fluidos que salen)'],
                ], false);
                continue;
            }
            if ($field === self::FIELD_CANTIDAD) {
                $issues[] = ClinicalCaptureIssueFactory::make($category, $index, $field, [
                    ['value' => '500', 'label' => '500 ml'],
                    ['value' => '1000', 'label' => '1000 ml'],
                    ['value' => '1500', 'label' => '1500 ml'],
                    ['value' => '2000', 'label' => '2000 ml'],
                    ['value' => '2200', 'label' => '2200 ml'],
                ], true);
            }
        }

        return $issues;
    }
}
