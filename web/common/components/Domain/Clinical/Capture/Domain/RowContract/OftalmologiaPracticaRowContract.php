<?php

namespace common\components\Domain\Clinical\Capture\Domain\RowContract;

use common\components\Domain\Clinical\Capture\Domain\Model\ClinicalCaptureRowCompleteness;

/**
 * Domain contract for an ophthalmology practice.
 */
final class OftalmologiaPracticaRowContract
{
    public const MODELO = 'OphthalmologyPractice';

    public const FIELD_CODIGO = 'Codigo';
    public const FIELD_OJO = 'Ojo';
    public const FIELD_PRUEBA = 'Prueba';
    public const FIELD_ESTADO = 'Estado';
    public const FIELD_INFORME = 'Informe';
    public const FIELD_TIPO = 'Tipo';

    /** @var list<string> claves que la IA debe devolver por fila (fuente única del prompt) */
    public const PROMPT_FIELDS = [
        self::FIELD_CODIGO,
        self::FIELD_OJO,
        self::FIELD_PRUEBA,
        self::FIELD_ESTADO,
        self::FIELD_INFORME,
        self::FIELD_TIPO,
    ];

    /** @var list<string> valores admitidos del ojo examinado */
    public const EYE_VALUES = ['OD', 'OI', 'AO'];

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
        $prueba = self::extractPrueba($row);
        $missing = ($codigo === '' && $prueba === '')
            ? [self::FIELD_CODIGO, self::FIELD_PRUEBA]
            : [];
        if ($codigo !== '') {
            $label = $codigo;
        } elseif ($prueba !== '') {
            $label = $prueba;
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
            'conceptId',
        ]) ?? '';
    }

    /**
     * Ojo examinado: OD, OI o AO.
     *
     * @param array<string, mixed>|string $row
     */
    public static function extractOjo($row): string
    {
        if (!is_array($row)) {
            return '';
        }
        $raw = ExtractedRowFields::firstNonEmpty($row, [
            self::FIELD_OJO,
            'ojo',
            'eye',
            'lado',
        ]);
        if ($raw === null) {
            return '';
        }
        $value = strtoupper(trim($raw));

        return in_array($value, self::EYE_VALUES, true) ? $value : '';
    }

    /**
     * @param array<string, mixed>|string $row
     */
    public static function extractPrueba($row): string
    {
        if (!is_array($row)) {
            return '';
        }

        return ExtractedRowFields::firstNonEmpty($row, [
            self::FIELD_PRUEBA,
            'prueba',
            'practica',
            'estudio',
        ]) ?? '';
    }

    /**
     * @param array<string, mixed>|string $row
     */
    public static function extractEstado($row): string
    {
        if (!is_array($row)) {
            return '';
        }

        return ExtractedRowFields::firstNonEmpty($row, [
            self::FIELD_ESTADO,
            'estado',
            'resultado_estado',
        ]) ?? '';
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

    /**
     * @param array<string, mixed>|string $row
     */
    public static function extractTipo($row): string
    {
        if (!is_array($row)) {
            return '';
        }

        return ExtractedRowFields::firstNonEmpty($row, [
            self::FIELD_TIPO,
            'tipo',
            'type',
        ]) ?? '';
    }
}
