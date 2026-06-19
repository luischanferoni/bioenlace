<?php

namespace common\components\Domain\Organization\Service\ProfesionalEfectorServicio;

/**
 * Duración en días calendario (inclusive) para rangos de licencia / condición laboral.
 */
final class LicenciaRangoDiasFormatter
{
    /**
     * Días inclusive entre inicio y fin (ambos ISO Y-m-d). Null si no se puede calcular.
     */
    public static function countInclusiveCalendarDays(?string $fechaInicio, ?string $fechaFin): ?int
    {
        $start = self::parseYmd($fechaInicio);
        $end = self::parseYmd($fechaFin);
        if ($start === null || $end === null) {
            return null;
        }
        if ($end <= $start) {
            return null;
        }

        return $start->diff($end)->days + 1;
    }

    /**
     * Leyenda corta para UI: "5 días", "1 día", o vacío si no aplica.
     */
    public static function leyendaFromIso(?string $fechaInicio, ?string $fechaFin): string
    {
        $n = self::countInclusiveCalendarDays($fechaInicio, $fechaFin);
        if ($n === null || $n < 1) {
            return '';
        }

        return $n === 1 ? '1 día' : $n . ' días';
    }

    /**
     * Texto de ayuda bajo los campos de fecha (formulario).
     */
    public static function hintFormularioFromIso(?string $fechaInicio, ?string $fechaFin): string
    {
        $leyenda = self::leyendaFromIso($fechaInicio, $fechaFin);
        if ($leyenda !== '') {
            return 'Duración: ' . $leyenda;
        }
        $fi = trim((string) $fechaInicio);
        $ff = trim((string) $fechaFin);
        if ($fi !== '' && $ff === '') {
            return 'Indicá la fecha de fin para ver la duración.';
        }
        if ($fi === '' && $ff !== '') {
            return 'Indicá la fecha de inicio para ver la duración.';
        }

        return 'Seleccioná fecha de inicio y fin para ver la duración.';
    }

    private static function parseYmd(?string $iso): ?\DateTimeImmutable
    {
        $s = trim((string) $iso);
        if ($s === '') {
            return null;
        }
        try {
            return new \DateTimeImmutable($s);
        } catch (\Throwable $e) {
            return null;
        }
    }
}
