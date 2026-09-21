<?php

namespace common\components\Domain\Clinical\Capture\Domain\Policy;

use common\components\Domain\Clinical\Capture\Domain\Model\ClinicalCaptureRowCompleteness;

/** Contrato Domain: régimen/dieta (`ConsultaRegimen`). */
final class RegimenRowContract
{
    public const MODELO = 'ConsultaRegimen';

    public const FIELD_INDICACIONES = 'Indicaciones';

    public static function matchesModelo(string $modelo): bool
    {
        return ExtractedRowFields::matchesModelo($modelo, self::MODELO);
    }

    /**
     * @param array<string, mixed>|string $row
     */
    public static function assess($row, string $categoryTitle = '', int $index = 0): ClinicalCaptureRowCompleteness
    {
        $indicaciones = self::extractIndicaciones($row);
        $missing = $indicaciones === '' ? [self::FIELD_INDICACIONES] : [];
        $label = $indicaciones !== '' ? mb_substr($indicaciones, 0, 80) : 'ítem';

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
    public static function extractIndicaciones($row): string
    {
        if (($s = ExtractedRowFields::stringRow($row)) !== null) {
            return $s;
        }
        if (!is_array($row)) {
            return '';
        }

        return ExtractedRowFields::firstNonEmpty($row, [
            self::FIELD_INDICACIONES,
            'indicaciones',
            'texto',
            'display',
            'termino',
        ]) ?? '';
    }
}
