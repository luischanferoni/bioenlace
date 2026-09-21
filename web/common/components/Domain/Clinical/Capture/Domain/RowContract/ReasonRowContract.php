<?php

namespace common\components\Domain\Clinical\Capture\Domain\RowContract;

use common\components\Domain\Clinical\Capture\Domain\Model\ClinicalCaptureRowCompleteness;

/**
 * Contrato de fila Domain para tipología EncounterReason (motivo de consulta).
 * Sustituye la semántica que vivía en EncounterReasonInput para completitud/resoluciones.
 */
final class ReasonRowContract
{
    public const MODELO = 'EncounterReason';

    public const FIELD_MOTIVO = 'Motivo';
    public const FIELD_CODIGO = 'Codigo';

    public static function matchesModelo(string $modelo): bool
    {
        $modelo = trim($modelo);
        if ($modelo === '' || $modelo === self::MODELO) {
            return $modelo === self::MODELO;
        }
        if (str_contains($modelo, '\\')) {
            $pos = strrpos($modelo, '\\');

            return ($pos === false ? $modelo : substr($modelo, $pos + 1)) === self::MODELO;
        }

        return $modelo === self::MODELO;
    }

    /**
     * @param array<string, mixed>|string $row
     */
    public static function assess($row, string $categoryTitle = '', int $index = 0): ClinicalCaptureRowCompleteness
    {
        $motivo = self::extractMotivo($row);
        $missing = $motivo === '' ? [self::FIELD_MOTIVO] : [];

        return new ClinicalCaptureRowCompleteness(
            $missing,
            $motivo !== '' ? $motivo : 'motivo',
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
        if ($field === self::FIELD_MOTIVO) {
            $row['texto'] = $value;
            $row['display'] = $value;
        }
        if ($field === self::FIELD_CODIGO) {
            $row['codigo'] = $value;
        }

        return $row;
    }

    /**
     * @param array<string, mixed>|string $row
     */
    public static function extractMotivo($row): string
    {
        if (is_string($row)) {
            return trim($row);
        }
        if (!is_array($row)) {
            return '';
        }

        return self::firstNonEmpty($row, [
            self::FIELD_MOTIVO,
            'texto',
            'termino',
            'descripcion',
            'label',
            'display',
            'motivo',
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

        return self::firstNonEmpty($row, [self::FIELD_CODIGO, 'codigo', 'code']) ?? '';
    }

    /**
     * @param array<string, mixed> $row
     * @param list<string> $keys
     */
    private static function firstNonEmpty(array $row, array $keys): ?string
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
