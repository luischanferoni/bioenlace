<?php

namespace common\components\Domain\Clinical\Emergency\Enum;

/**
 * Destinos de egreso / conducta de guardia (ENUM en BD, literales MAYÚSCULAS).
 */
final class GuardiaEgresoDestino
{
    public const ALTA_DOMICILIARIA = 'ALTA_DOMICILIARIA';
    public const OBSERVACION = 'OBSERVACION';
    public const INTERNACION = 'INTERNACION';
    public const QUIROFANO = 'QUIROFANO';
    public const DERIVACION = 'DERIVACION';
    public const FUGA = 'FUGA';
    public const DEFUNCION = 'DEFUNCION';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return [
            self::ALTA_DOMICILIARIA,
            self::OBSERVACION,
            self::INTERNACION,
            self::QUIROFANO,
            self::DERIVACION,
            self::FUGA,
            self::DEFUNCION,
        ];
    }

    public static function label(string $code): string
    {
        $map = [
            self::ALTA_DOMICILIARIA => 'Alta domiciliaria',
            self::OBSERVACION => 'Pase a observación',
            self::INTERNACION => 'Pase a internación / UCI',
            self::QUIROFANO => 'Pase a quirófano',
            self::DERIVACION => 'Derivación a otra institución',
            self::FUGA => 'Fuga / abandono',
            self::DEFUNCION => 'Defunción',
        ];

        return $map[$code] ?? $code;
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        $out = [];
        foreach (self::values() as $code) {
            $out[] = [
                'value' => $code,
                'label' => self::label($code),
            ];
        }

        return $out;
    }

    public static function requiresPautasAlarma(string $code): bool
    {
        return $code === self::ALTA_DOMICILIARIA;
    }

    public static function requiresEfectorDerivacion(string $code): bool
    {
        return $code === self::DERIVACION;
    }

    public static function requestsInternacion(string $code): bool
    {
        return $code === self::INTERNACION;
    }
}
