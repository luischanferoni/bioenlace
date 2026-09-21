<?php

namespace common\components\Domain\Clinical\Capture\Domain\RowContract;

use common\components\Domain\Clinical\Capture\Domain\Model\ClinicalCaptureRowCompleteness;

/**
 * Contrato Domain: ítem odontológico
 * (`ConsultaOdontologiaPracticas|Diagnosticos|Estados`).
 */
final class OdontologiaItemRowContract
{
    public const MODELOS = [
        'ConsultaOdontologiaPracticas',
        'ConsultaOdontologiaDiagnosticos',
        'ConsultaOdontologiaEstados',
    ];

    public const FIELD_TIPO = 'Tipo';
    public const FIELD_CODIGO = 'Codigo';

    public static function matchesModelo(string $modelo): bool
    {
        return ExtractedRowFields::matchesModelo($modelo, ...self::MODELOS);
    }

    /**
     * @param array<string, mixed>|string $row
     */
    public static function assess($row, string $categoryTitle = '', int $index = 0): ClinicalCaptureRowCompleteness
    {
        $tipo = self::extractTipo($row);
        $codigo = self::extractCodigo($row);
        $missing = ($tipo === '' && $codigo === '')
            ? [self::FIELD_TIPO, self::FIELD_CODIGO]
            : [];
        if ($codigo !== '') {
            $label = $codigo;
        } elseif ($tipo !== '') {
            $label = $tipo;
        } else {
            $label = 'ítem';
        }

        return new ClinicalCaptureRowCompleteness($missing, $label, []);
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public static function applyResolution(array $row, string $field, mixed $value): array
    {
        $row[$field] = $value;

        return $row;
    }

    /**
     * @param array<string, mixed>|string $row
     */
    public static function extractTipo($row): string
    {
        if (!is_array($row)) {
            return '';
        }

        return ExtractedRowFields::firstNonEmpty($row, [self::FIELD_TIPO, 'tipo', 'type']) ?? '';
    }

    /**
     * @param array<string, mixed>|string $row
     */
    public static function extractCodigo($row): string
    {
        if (($s = ExtractedRowFields::stringRow($row)) !== null) {
            return $s;
        }
        if (!is_array($row)) {
            return '';
        }

        return ExtractedRowFields::firstNonEmpty($row, [
            self::FIELD_CODIGO,
            'codigo',
            'code',
            'termino',
            'texto',
            'display',
            'label',
        ]) ?? '';
    }
}
