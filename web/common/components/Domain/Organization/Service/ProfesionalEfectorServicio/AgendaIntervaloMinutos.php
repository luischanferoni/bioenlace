<?php

namespace common\components\Domain\Organization\Service\ProfesionalEfectorServicio;

/**
 * Intervalos permitidos para la grilla de turnos (única palanca de granularidad).
 */
final class AgendaIntervaloMinutos
{
    public const DEFAULT = 15;

  /** @var list<int> */
    public const ALLOWED = [15, 20, 30, 45, 60];

    /** Máximo de cambios de intervalo por PES y año calendario. */
    public const MAX_CAMBIOS_INTERVALO_POR_ANIO = 2;

    public static function normalize($value): int
    {
        $n = (int) $value;
        if (in_array($n, self::ALLOWED, true)) {
            return $n;
        }

        return self::DEFAULT;
    }

    public static function isAllowed(int $value): bool
    {
        return in_array($value, self::ALLOWED, true);
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function selectOptions(): array
    {
        $out = [];
        foreach (self::ALLOWED as $m) {
            $out[] = [
                'value' => (string) $m,
                'label' => $m . ' minutos',
            ];
        }

        return $out;
    }
}
