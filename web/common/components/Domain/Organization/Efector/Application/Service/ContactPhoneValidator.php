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
