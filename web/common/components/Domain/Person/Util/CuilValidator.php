<?php

namespace common\components\Domain\Person\Util;

/**
 * Validación de CUIL argentino (11 dígitos, dígito verificador AFIP).
 */
final class CuilValidator
{
    /** @var list<string> */
    private const VALID_PREFIXES = ['20', '23', '24', '27', '30', '33', '34'];

    /** @var list<int> */
    private const WEIGHTS = [5, 4, 3, 2, 7, 6, 5, 4, 3, 2];

    public static function normalize(string $cuil): string
    {
        return preg_replace('/\D+/', '', $cuil) ?? '';
    }

    public static function isValid(string $cuil): bool
    {
        $normalized = self::normalize($cuil);
        if (strlen($normalized) !== 11 || !ctype_digit($normalized)) {
            return false;
        }

        $prefix = substr($normalized, 0, 2);
        if (!in_array($prefix, self::VALID_PREFIXES, true)) {
            return false;
        }

        return (int) $normalized[10] === self::checkDigitForBody(substr($normalized, 0, 10));
    }

    /**
     * Construye CUIL válido para seeds/demos (prefijo 20 + DNI padded a 8 dígitos).
     */
    public static function buildFromDni(string $dni, string $prefix = '20'): string
    {
        $digits = preg_replace('/\D+/', '', $dni) ?? '';
        if ($digits === '') {
            throw new \InvalidArgumentException('DNI vacío para derivar CUIL.');
        }
        if (strlen($digits) > 8) {
            $digits = substr($digits, -8);
        }
        $body = $prefix . str_pad($digits, 8, '0', STR_PAD_LEFT);

        return $body . (string) self::checkDigitForBody($body);
    }

    private static function checkDigitForBody(string $body): int
    {
        if (strlen($body) !== 10 || !ctype_digit($body)) {
            throw new \InvalidArgumentException('Cuerpo CUIL inválido.');
        }

        $sum = 0;
        for ($i = 0; $i < 10; $i++) {
            $sum += (int) $body[$i] * self::WEIGHTS[$i];
        }

        $mod = $sum % 11;
        if ($mod === 0) {
            return 0;
        }
        if ($mod === 1) {
            return 9;
        }

        return 11 - $mod;
    }
}
