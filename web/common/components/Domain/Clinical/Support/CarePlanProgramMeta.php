<?php

namespace common\components\Domain\Clinical\Support;

/**
 * Metadatos de programa en `care_plan.description` (JSON) sin columna extra v1.
 *
 * Formato: {"program":{"occurrenceTotal":10,"occurrenceCount":3}}
 */
final class CarePlanProgramMeta
{
    /**
     * @return array{occurrenceTotal: int, occurrenceCount: int}
     */
    public static function parse(?string $description): array
    {
        if ($description === null || $description === '') {
            return ['occurrenceTotal' => 0, 'occurrenceCount' => 0];
        }
        $data = json_decode($description, true);
        if (!is_array($data) || !isset($data['program']) || !is_array($data['program'])) {
            return ['occurrenceTotal' => 0, 'occurrenceCount' => 0];
        }
        $p = $data['program'];

        return [
            'occurrenceTotal' => max(0, (int) ($p['occurrenceTotal'] ?? 0)),
            'occurrenceCount' => max(0, (int) ($p['occurrenceCount'] ?? 0)),
        ];
    }

    public static function encode(int $occurrenceTotal, int $occurrenceCount): string
    {
        return json_encode([
            'program' => [
                'occurrenceTotal' => max(0, $occurrenceTotal),
                'occurrenceCount' => max(0, $occurrenceCount),
            ],
        ], JSON_UNESCAPED_UNICODE);
    }

    public static function isExhausted(?string $description): bool
    {
        $meta = self::parse($description);
        if ($meta['occurrenceTotal'] <= 0) {
            return false;
        }

        return $meta['occurrenceCount'] >= $meta['occurrenceTotal'];
    }
}
