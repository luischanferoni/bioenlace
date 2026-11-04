<?php

namespace common\components\Domain\Organization\Efector\Application\Service;

/**
 * Validación ligera de teléfono en formato E.164 (opcional).
 *
 * Solo valida cuando hay un valor; un teléfono vacío es válido (campo opcional).
 * Para validación regional completa usar libphonenumber; esta regla alcanza
 * para el alta (sin exigir teléfono).
 */
final class ContactPhoneValidator
{
    /**
     * Normaliza un celular a E.164 (`+549…` para Argentina).
     *
     * Acepta:
     * - `+549XXXXXXXXXX` (E.164 móvil AR)
     * - `549XXXXXXXXXX` (sin `+`)
     * - `XXXXXXXXXX` (10 dígitos: área + número de AR)
     * - `+<país><número>` de 8 a 15 dígitos para el resto de los países
     *
     * Devuelve `null` si viene vacío y el valor original si no se puede
     * normalizar (dejando que `isValidMobile()` reporte el error).
     */
    public static function normalizeMobile(?string $phone): ?string
    {
        $raw = trim((string) $phone);
        if ($raw === '') {
            return null;
        }

        $hasPlus = str_starts_with($raw, '+');
        $digits = (string) preg_replace('/\D+/', '', $raw);
        if ($digits === '') {
            return $raw;
        }

        if (preg_match('/^549\d{10}$/', $digits) === 1) {
            return '+' . $digits;
        }
        // Formato nacional argentino: 0 + área + 15 + número (011 15 1234-5678).
        // El significativo nacional (área + número) debe sumar 10 dígitos.
        if (!$hasPlus && preg_match('/^0(\d{2,4}?)15(\d{6,8})$/', $digits, $matches) === 1) {
            $national = $matches[1] . $matches[2];
            if (strlen($national) === 10) {
                return '+549' . $national;
            }
        }
        if (!$hasPlus && preg_match('/^\d{10}$/', $digits) === 1) {
            return '+549' . $digits;
        }

        return $hasPlus ? '+' . $digits : $raw;
    }

    /**
     * Celular en E.164: en Argentina exige el `9` de móvil (+549 + 10 dígitos).
     * Un valor vacío es válido (el teléfono es opcional).
     */
    public static function isValidMobile(?string $phone): bool
    {
        if ($phone === null || trim($phone) === '') {
            return true;
        }

        $normalized = self::normalizeMobile($phone);
        if ($normalized === null) {
            return true;
        }

        if (preg_match('/^\+549\d{10}$/', $normalized) === 1) {
            return true;
        }
        if (str_starts_with($normalized, '+54')) {
            // Un fijo argentino (+54 sin el 9) no sirve para el alta.
            return false;
        }

        return preg_match('/^\+[1-9]\d{7,14}$/', $normalized) === 1;
    }

    public static function assertValidMobile(?string $phone, string $label = 'Teléfono'): void
    {
        if (!self::isValidMobile($phone)) {
            throw new \InvalidArgumentException(
                $label . ' inválido. Ingresá un celular en formato internacional (ej. +5491123456789).'
            );
        }
    }

    /**
     * E.164: opcional '+', 6 a 15 dígitos, sin separadores.
     */
    public static function isValid(?string $phone): bool
    {
        if ($phone === null) {
            return true;
        }
        $phone = trim($phone);
        if ($phone === '') {
            return true;
        }

        return (bool) preg_match('/^\+?[1-9]\d{6,14}$/', $phone);
    }

    public static function assertValid(?string $phone, string $label = 'Teléfono'): void
    {
        if (!self::isValid($phone)) {
            throw new \InvalidArgumentException($label . ' inválido. Usá formato internacional (ej. +54…).');
        }
    }
}
