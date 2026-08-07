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

    public const MODO_CLINICO = 'clinico';
    public const MODO_ADMINISTRATIVO = 'administrativo';

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

    /**
     * Destinos de egreso médico (hubo o está en atención).
     *
     * @return list<string>
     */
    public static function valuesClinicos(): array
    {
        return [
            self::ALTA_DOMICILIARIA,
            self::OBSERVACION,
            self::INTERNACION,
            self::QUIROFANO,
            self::DERIVACION,
            self::DEFUNCION,
            self::FUGA,
        ];
    }

    /**
     * Destinos de egreso administrativo (sin atención médica): abandono / retiro.
     *
     * @return list<string>
     */
    public static function valuesAdministrativos(): array
    {
        return [
            self::FUGA,
        ];
    }

    public static function isAdministrativo(string $code): bool
    {
        return in_array($code, self::valuesAdministrativos(), true);
    }

    public static function label(string $code): string
    {
        $map = [
            self::ALTA_DOMICILIARIA => 'Alta domiciliaria',
            self::OBSERVACION => 'Pase a observación',
            self::INTERNACION => 'Pase a internación / UCI',
            self::QUIROFANO => 'Pase a quirófano',
            self::DERIVACION => 'Derivación a otra institución',
            self::FUGA => 'Fuga / abandono / retiro sin atención',
            self::DEFUNCION => 'Defunción',
        ];

        return $map[$code] ?? $code;
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return self::optionsForModo(self::MODO_CLINICO);
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function optionsForModo(string $modo): array
    {
        $codes = $modo === self::MODO_ADMINISTRATIVO
            ? self::valuesAdministrativos()
            : self::valuesClinicos();
        $out = [];
        foreach ($codes as $code) {
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
