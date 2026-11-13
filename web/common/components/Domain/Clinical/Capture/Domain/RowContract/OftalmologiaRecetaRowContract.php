<?php

namespace common\components\Domain\Clinical\Capture\Domain\RowContract;

use common\components\Domain\Clinical\Capture\Domain\Model\ClinicalCaptureRowCompleteness;

/**
 * Domain contract for a lens prescription (one row per prescription, both eyes).
 */
final class OftalmologiaRecetaRowContract
{
    public const MODELO = 'OphthalmologyLensPrescription';

    public const FIELD_OD_ESFERA = 'OD esfera';
    public const FIELD_OD_CILINDRO = 'OD cilindro';
    public const FIELD_OD_EJE = 'OD eje';
    public const FIELD_OD_ADICION = 'OD adicion';
    public const FIELD_OI_ESFERA = 'OI esfera';
    public const FIELD_OI_CILINDRO = 'OI cilindro';
    public const FIELD_OI_EJE = 'OI eje';
    public const FIELD_OI_ADICION = 'OI adicion';

    /** @var list<string> claves que la IA debe devolver por fila (fuente única del prompt) */
    public const PROMPT_FIELDS = [
        self::FIELD_OD_ESFERA,
        self::FIELD_OD_CILINDRO,
        self::FIELD_OD_EJE,
        self::FIELD_OD_ADICION,
        self::FIELD_OI_ESFERA,
        self::FIELD_OI_CILINDRO,
        self::FIELD_OI_EJE,
        self::FIELD_OI_ADICION,
    ];

    public static function matchesModelo(string $modelo): bool
    {
        return ExtractedRowFields::matchesModelo($modelo, self::MODELO);
    }

    /**
     * Una receta sin ningún valor no aporta nada: se marca incompleta.
     *
     * @param array<string, mixed>|string $row
     */
    public static function assess($row, string $categoryTitle = '', int $index = 0): ClinicalCaptureRowCompleteness
    {
        $values = self::values($row);
        $hasValue = false;
        foreach ($values as $value) {
            if ($value !== '') {
                $hasValue = true;
                break;
            }
        }

        return new ClinicalCaptureRowCompleteness(
            $hasValue ? [] : self::PROMPT_FIELDS,
            self::label($values),
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
     * Valores normalizados por clave de `PROMPT_FIELDS`.
     *
     * @param array<string, mixed>|string $row
     * @return array<string, string>
     */
    public static function values($row): array
    {
        $out = [];
        foreach (self::PROMPT_FIELDS as $field) {
            $out[$field] = self::value($row, $field);
        }

        return $out;
    }

    /**
     * @param array<string, mixed>|string $row
     */
    public static function value($row, string $field): string
    {
        if (!is_array($row)) {
            return '';
        }
        $aliases = self::aliases()[$field] ?? [$field];

        return ExtractedRowFields::firstNonEmpty($row, $aliases) ?? '';
    }

    /**
     * @return array<string, list<string>>
     */
    private static function aliases(): array
    {
        return [
            self::FIELD_OD_ESFERA => [self::FIELD_OD_ESFERA, 'od_esfera', 'esfera_od'],
            self::FIELD_OD_CILINDRO => [self::FIELD_OD_CILINDRO, 'od_cilindro', 'cilindro_od'],
            self::FIELD_OD_EJE => [self::FIELD_OD_EJE, 'od_eje', 'eje_od'],
            self::FIELD_OD_ADICION => [self::FIELD_OD_ADICION, 'od_add', 'od_adicion', 'adicion_od'],
            self::FIELD_OI_ESFERA => [self::FIELD_OI_ESFERA, 'oi_esfera', 'esfera_oi'],
            self::FIELD_OI_CILINDRO => [self::FIELD_OI_CILINDRO, 'oi_cilindro', 'cilindro_oi'],
            self::FIELD_OI_EJE => [self::FIELD_OI_EJE, 'oi_eje', 'eje_oi'],
            self::FIELD_OI_ADICION => [self::FIELD_OI_ADICION, 'oi_add', 'oi_adicion', 'adicion_oi'],
        ];
    }

    /**
     * @param array<string, string> $values
     */
    private static function label(array $values): string
    {
        $parts = [];
        foreach ($values as $field => $value) {
            if ($value !== '') {
                $parts[] = $field . ' ' . $value;
                if (count($parts) >= 2) {
                    break;
                }
            }
        }

        return $parts === [] ? 'receta' : implode(' · ', $parts);
    }
}
