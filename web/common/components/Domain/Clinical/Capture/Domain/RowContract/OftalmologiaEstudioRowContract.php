<?php

namespace common\components\Domain\Clinical\Capture\Domain\RowContract;

use common\components\Domain\Clinical\Capture\Domain\Model\ClinicalCaptureRowCompleteness;

/** Contrato Domain: estudio oftalmológico (`ConsultaPracticasOftalmologiaEstudios`). */
final class OftalmologiaEstudioRowContract
{
    public const MODELO = 'ConsultaPracticasOftalmologiaEstudios';

    public const FIELD_CODIGO = 'Codigo';
    public const FIELD_OJO = 'Ojo';
    public const FIELD_INFORME = 'Informe';

    public static function matchesModelo(string $modelo): bool
    {
        return ExtractedRowFields::matchesModelo($modelo, self::MODELO);
    }

    /**
     * @param array<string, mixed>|string $row
     */
    public static function assess($row, string $categoryTitle = '', int $index = 0): ClinicalCaptureRowCompleteness
    {
        $codigo = self::extractCodigo($row);
        $informe = self::extractInforme($row);
        $missing = ($codigo === '' && $informe === '')
            ? [self::FIELD_CODIGO, self::FIELD_INFORME]
            : [];
        if ($codigo !== '') {
            $label = $codigo;
        } elseif ($informe !== '') {
            $label = mb_substr($informe, 0, 80);
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
    public static function extractCodigo($row): string
    {
        if (!is_array($row)) {
            return '';
        }

        return ExtractedRowFields::firstNonEmpty($row, [
            self::FIELD_CODIGO,
            'codigo',
            'code',
            'prueba',
        ]) ?? '';
    }

    /**
     * @param array<string, mixed>|string $row
     */
    public static function extractOjo($row): string
    {
        if (!is_array($row)) {
            return '';
        }

        return ExtractedRowFields::firstNonEmpty($row, [self::FIELD_OJO, 'ojo', 'eye']) ?? '';
    }

    /**
     * @param array<string, mixed>|string $row
     */
    public static function extractInforme($row): string
    {
        if (($s = ExtractedRowFields::stringRow($row)) !== null) {
            return $s;
        }
        if (!is_array($row)) {
            return '';
        }

        return ExtractedRowFields::firstNonEmpty($row, [
            self::FIELD_INFORME,
            'informe',
            'resultado',
            'texto',
            'display',
        ]) ?? '';
    }
}
