<?php

namespace common\components\Domain\Clinical\Capture\Domain\RowContract;

use common\components\Domain\Clinical\Capture\Domain\Model\ClinicalCaptureRowCompleteness;

/**
 * Domain contract for dental practices, diagnoses, and tooth status.
 */
final class OdontologiaItemRowContract
{
    public const MODELOS = [
        'EncounterDentalPractice',
        'EncounterDentalDiagnosis',
        'EncounterDentalStatus',
    ];

    public const FIELD_TIPO = 'Tipo';
    public const FIELD_CODIGO = 'Codigo';
    public const FIELD_PIEZA = 'Pieza';
    public const FIELD_CARAS = 'Caras';
    public const FIELD_TIEMPO = 'Tiempo';

    /** @var list<string> claves que la IA debe devolver por fila (fuente única del prompt) */
    public const PROMPT_FIELDS = [
        self::FIELD_TIPO,
        self::FIELD_CODIGO,
        self::FIELD_PIEZA,
        self::FIELD_CARAS,
        self::FIELD_TIEMPO,
    ];

    /** @var list<string> momento del tratamiento; `PRESENTE` es el default de dominio */
    public const TIME_QUALIFIERS = ['PASADA', 'PRESENTE', 'FUTURA'];

    public const DEFAULT_TIME_QUALIFIER = 'PRESENTE';

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
            'conceptId',
            'termino',
            'texto',
            'display',
            'label',
        ]) ?? '';
    }

    /**
     * Etiqueta legible de la fila (término SNOMED o texto dictado).
     *
     * @param array<string, mixed>|string $row
     */
    public static function extractTermino($row): ?string
    {
        if (($s = ExtractedRowFields::stringRow($row)) !== null) {
            return $s;
        }
        if (!is_array($row)) {
            return null;
        }

        return ExtractedRowFields::firstNonEmpty($row, [
            'termino',
            'término',
            'Nombre de la practica',
            'nombre',
            'display',
            'texto',
            'label',
        ]);
    }

    /**
     * Pieza dental en notación FDI (p. ej. 11, 48, 85).
     *
     * @param array<string, mixed>|string $row
     */
    public static function extractPieza($row): string
    {
        if (!is_array($row)) {
            return '';
        }

        return ExtractedRowFields::firstNonEmpty($row, [
            self::FIELD_PIEZA,
            'pieza',
            'diente',
            'tooth',
            'tooth_number',
        ]) ?? '';
    }

    /**
     * Caras/superficies de la pieza (M, O, D, V, L, P).
     *
     * @param array<string, mixed>|string $row
     */
    public static function extractCaras($row): string
    {
        if (!is_array($row)) {
            return '';
        }

        return ExtractedRowFields::firstNonEmpty($row, [
            self::FIELD_CARAS,
            'caras',
            'superficies',
            'superficie',
            'surfaces',
        ]) ?? '';
    }

    /**
     * Normaliza el momento del tratamiento a `TIME_QUALIFIERS`.
     *
     * Un valor no reconocido se descarta (null): la fila queda sin momento y el
     * profesional lo elige en la revisión. No se inventa `PRESENTE` acá porque
     * `requeridosPrompt` no distingue "sin dato" de "dato inválido".
     *
     * @param array<string, mixed>|string $row
     */
    public static function extractTiempo($row): ?string
    {
        if (!is_array($row)) {
            return null;
        }
        $raw = ExtractedRowFields::firstNonEmpty($row, [
            self::FIELD_TIEMPO,
            'tiempo',
            'momento',
            'time_qualifier',
            'periodo',
        ]);
        if ($raw === null) {
            return null;
        }
        $upper = strtoupper(trim($raw));

        return in_array($upper, self::TIME_QUALIFIERS, true) ? $upper : null;
    }
}
