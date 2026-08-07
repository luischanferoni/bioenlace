<?php

namespace common\components\Domain\Clinical\Emergency\Service;

/**
 * Normalización y validación de SV opcionales del triage (TA / FC).
 */
final class GuardiaTriageVitalsValidator
{
    public const BP_SYS_MIN = 50;
    public const BP_SYS_MAX = 250;
    public const BP_DIA_MIN = 30;
    public const BP_DIA_MAX = 150;
    public const HR_MIN = 20;
    public const HR_MAX = 250;

    /**
     * @param array<string, mixed>|null $vitals
     * @return array<string, int>|null
     * @throws \InvalidArgumentException
     */
    public static function normalize(?array $vitals): ?array
    {
        if ($vitals === null || $vitals === []) {
            return null;
        }

        $out = [];
        $specs = [
            'bp_sys' => [self::BP_SYS_MIN, self::BP_SYS_MAX, 'TA sistólica'],
            'bp_dia' => [self::BP_DIA_MIN, self::BP_DIA_MAX, 'TA diastólica'],
            'hr' => [self::HR_MIN, self::HR_MAX, 'FC'],
        ];

        foreach ($specs as $key => [$min, $max, $label]) {
            if (!array_key_exists($key, $vitals)) {
                continue;
            }
            $raw = $vitals[$key];
            if ($raw === null || $raw === '') {
                continue;
            }
            $s = trim((string) $raw);
            if ($s === '') {
                continue;
            }
            if (!preg_match('/^\d{2,3}$/', $s)) {
                throw new \InvalidArgumentException(
                    $label . ': ingresá un entero de 2 o 3 dígitos.'
                );
            }
            $n = (int) $s;
            if ($n < $min || $n > $max) {
                throw new \InvalidArgumentException(
                    $label . ' debe estar entre ' . $min . ' y ' . $max . '.'
                );
            }
            $out[$key] = $n;
        }

        if (isset($out['bp_sys'], $out['bp_dia']) && $out['bp_sys'] <= $out['bp_dia']) {
            throw new \InvalidArgumentException(
                'TA sistólica debe ser mayor que la diastólica.'
            );
        }

        return $out === [] ? null : $out;
    }

    /**
     * Acepta vitals anidados o campos planos bp_sys / bp_dia / hr.
     *
     * @param array<string, mixed> $body
     * @return array<string, int>|null
     */
    public static function normalizeFromBody(array $body): ?array
    {
        $vitals = $body['vitals'] ?? null;
        if (!is_array($vitals)) {
            $vitals = [];
        }
        foreach (['bp_sys', 'bp_dia', 'hr'] as $k) {
            if (array_key_exists($k, $body) && !array_key_exists($k, $vitals)) {
                $vitals[$k] = $body[$k];
            }
        }

        return self::normalize($vitals === [] ? null : $vitals);
    }
}
