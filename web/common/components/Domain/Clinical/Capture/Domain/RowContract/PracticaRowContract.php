<?php

namespace common\components\Domain\Clinical\Capture\Domain\RowContract;

use common\components\Domain\Clinical\Capture\Domain\Model\ClinicalCaptureRowCompleteness;

/** Contrato Domain: práctica realizada (`ConsultaPracticas`). */
final class PracticaRowContract
{
    public const MODELO = 'ConsultaPracticas';

    public const FIELD_PRACTICA = 'Practica';
    public const FIELD_RESULTADO = 'Resultado';
    public const FIELD_CODIGO = 'Codigo';

    public static function matchesModelo(string $modelo): bool
    {
        return ExtractedRowFields::matchesModelo($modelo, self::MODELO);
    }

    /**
     * @param array<string, mixed>|string $row
     */
    public static function assess($row, string $categoryTitle = '', int $index = 0): ClinicalCaptureRowCompleteness
    {
        $practica = self::extractPractica($row);
        $missing = $practica === '' ? [self::FIELD_PRACTICA] : [];

        return new ClinicalCaptureRowCompleteness(
            $missing,
            $practica !== '' ? $practica : 'ítem',
            []
        );
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
    public static function extractPractica($row): string
    {
        if (($s = ExtractedRowFields::stringRow($row)) !== null) {
            return $s;
        }
        if (!is_array($row)) {
            return '';
        }

        return ExtractedRowFields::firstNonEmpty($row, [
            self::FIELD_PRACTICA,
            'practica',
            'termino',
            'texto',
            'display',
            'label',
        ]) ?? '';
    }

    /**
     * @param array<string, mixed>|string $row
     */
    public static function extractResultado($row): string
    {
        if (!is_array($row)) {
            return '';
        }

        return ExtractedRowFields::firstNonEmpty($row, [
            self::FIELD_RESULTADO,
            'resultado',
            'result',
            'valor',
        ]) ?? '';
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
            'conceptId',
        ]) ?? '';
    }
}
