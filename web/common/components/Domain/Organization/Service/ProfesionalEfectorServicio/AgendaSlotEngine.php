<?php

namespace common\components\Domain\Organization\Service\ProfesionalEfectorServicio;

use common\traits\AgendaHorarioSlotsTrait;

/**
 * Generación de slots y utilidades de intervalo (sin cupo/duración libre).
 */
final class AgendaSlotEngine
{
    use AgendaHorarioSlotsTrait;

    /**
     * @return list<string> HH:MM
     */
    public static function slotsParaDia(object $agendaLike, string $diaYmd, int $intervaloMinutos): array
    {
        $intervaloMinutos = AgendaIntervaloMinutos::normalize($intervaloMinutos);
        $columnas = ['lunes_2', 'martes_2', 'miercoles_2', 'jueves_2', 'viernes_2', 'sabado_2', 'domingo_2'];
        $nroDiaSemana = (int) date('N', strtotime($diaYmd));
        $colAgenda = $columnas[$nroDiaSemana - 1] ?? null;
        if ($colAgenda === null) {
            return [];
        }
        $colValue = $agendaLike->{$colAgenda} ?? null;
        if (!$colValue) {
            return [];
        }
        $horariosAgenda = array_map('intval', explode(',', (string) $colValue));
        if ($horariosAgenda === []) {
            return [];
        }

        return self::crearSlotsDesdeHorarios($horariosAgenda, $intervaloMinutos, false);
    }

    /**
     * @return list<string> HH:MM
     */
    public static function slotsParaDiaDesdeVersion(\common\models\ProfesionalEfectorServicioAgendaVersion $version, string $diaYmd): array
    {
        return self::slotsParaDia($version, $diaYmd, $version->getIntervaloMinutosEfectivo());
    }

    public static function horaEstaEnGrilla(object $agendaLike, string $diaYmd, string $hora, int $intervaloMinutos): bool
    {
        $hora = substr(trim($hora), 0, 5);
        foreach (self::slotsParaDia($agendaLike, $diaYmd, $intervaloMinutos) as $slot) {
            if (substr($slot, 0, 5) === $hora) {
                return true;
            }
        }

        return false;
    }

    /**
     * Vecinos más cercanos en la nueva grilla (para conflictos).
     *
     * @return array{antes: string|null, despues: string|null}
     */
    public static function vecinosEnGrilla(object $agendaLike, string $diaYmd, string $horaTurno, int $intervaloMinutos): array
    {
        $slots = self::slotsParaDia($agendaLike, $diaYmd, $intervaloMinutos);
        if ($slots === []) {
            return ['antes' => null, 'despues' => null];
        }
        $target = self::horaAMinutos(substr(trim($horaTurno), 0, 5));
        $antes = null;
        $despues = null;
        foreach ($slots as $s) {
            $m = self::horaAMinutos($s);
            if ($m <= $target) {
                $antes = substr($s, 0, 5);
            }
            if ($m >= $target && $despues === null) {
                $despues = substr($s, 0, 5);
            }
        }
        if ($antes === $despues && $antes !== null) {
            $idx = array_search($antes, array_map(static fn ($x) => substr($x, 0, 5), $slots), true);
            if ($idx !== false && $idx > 0) {
                $antes = substr($slots[$idx - 1], 0, 5);
            } elseif ($idx !== false && isset($slots[$idx + 1])) {
                $despues = substr($slots[$idx + 1], 0, 5);
                $antes = null;
            }
        }

        return ['antes' => $antes, 'despues' => $despues];
    }

    private static function horaAMinutos(string $hhmm): int
    {
        $parts = explode(':', $hhmm);
        $h = isset($parts[0]) ? (int) $parts[0] : 0;
        $m = isset($parts[1]) ? (int) $parts[1] : 0;

        return $h * 60 + $m;
    }

    public static function estimarSlotsPorDiaPromedio(object $agendaLike, int $intervaloMinutos): int
    {
        $intervaloMinutos = AgendaIntervaloMinutos::normalize($intervaloMinutos);
        $total = 0;
        $dias = 0;
        foreach (['lunes_2', 'martes_2', 'miercoles_2', 'jueves_2', 'viernes_2', 'sabado_2', 'domingo_2'] as $col) {
            $v = trim((string) ($agendaLike->{$col} ?? ''));
            if ($v === '') {
                continue;
            }
            $horarios = array_map('intval', explode(',', $v));
            $total += self::estimateSlotCapacityFromHorarios($horarios, $intervaloMinutos);
            $dias++;
        }

        return $dias > 0 ? (int) round($total / $dias) : 0;
    }
}
