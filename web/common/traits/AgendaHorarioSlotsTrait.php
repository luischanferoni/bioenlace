<?php

namespace common\traits;

use common\components\Services\ProfesionalEfectorServicio\AgendaIntervaloMinutos;
use common\components\Services\ProfesionalEfectorServicio\AgendaSlotEngine;

/**
 * Cálculo de slots HH:MM y validación de solapamiento entre agendas (columnas *_2).
 * Compartido por {@see \common\models\ProfesionalEfectorServicioAgenda} (tabla canónica; `agenda_rrhh` retirada vía {@see m260510_000001_drop_agenda_rrhh_table}).
 */
trait AgendaHorarioSlotsTrait
{
    private static function agendaHorarioColumnasSemana(): array
    {
        return ['lunes_2', 'martes_2', 'miercoles_2', 'jueves_2', 'viernes_2', 'sabado_2', 'domingo_2'];
    }

    /**
     * @param string $dia Fecha en Y-m-d
     * @return string[] horas HH:MM
     */
    public function getSlotsParaDia($dia): array
    {
        return AgendaSlotEngine::slotsParaDia($this, (string) $dia, $this->resolveIntervaloMinutosParaSlots());
    }

    public function resolveIntervaloMinutosParaSlots(): int
    {
        if (isset($this->intervalo_minutos) && (int) $this->intervalo_minutos > 0) {
            return AgendaIntervaloMinutos::normalize((int) $this->intervalo_minutos);
        }
        $duracion = isset($this->duracion_slot_minutos) ? (int) $this->duracion_slot_minutos : 0;
        if (AgendaIntervaloMinutos::isAllowed($duracion)) {
            return $duracion;
        }

        return AgendaIntervaloMinutos::DEFAULT;
    }

    /**
     * Genera la grilla HH:MM de cada intervalo contiguo de horas en agenda (hasta fin del último bloque + 60 min).
     * No limita por cupo de pacientes de la agenda: ese dato es de negocio; truncar la grilla ocultaba franjas (p. ej. tarde).
     *
     * @param int[] $horariosAgenda
     * @return string[]
     */
    public static function crearSlotsDesdeHorarios(array $horariosAgenda, $minutosXPaciente, $agregoSegundos)
    {
        $intervalos = [];
        $intervaloActual = [];
        for ($i = 0; $i < count($horariosAgenda); $i++) {
            if (empty($intervaloActual)) {
                $intervaloActual[] = $horariosAgenda[$i];
            } else {
                if ($horariosAgenda[$i] === $intervaloActual[count($intervaloActual) - 1] + 1) {
                    $intervaloActual[] = $horariosAgenda[$i];
                } else {
                    $intervalos[] = $intervaloActual;
                    $intervaloActual = [$horariosAgenda[$i]];
                }
            }
        }
        if (!empty($intervaloActual)) {
            $intervalos[] = $intervaloActual;
        }
        $slots = [];
        $minutosXPaciente = (int) $minutosXPaciente;
        $safetyMaxPorIntervalo = 10000;
        foreach ($intervalos as $horarios) {
            $inicio = new \DateTime(sprintf('%02d:00', $horarios[0]));
            $ultHora = new \DateTime(sprintf('%02d:00', $horarios[count($horarios) - 1]));
            $fin = clone $ultHora;
            $fin->modify('+60 minutes');
            $iter = 0;
            while ($inicio < $fin && $iter < $safetyMaxPorIntervalo) {
                $iter++;
                $slots[] = $inicio->format('H:i');
                if ($agregoSegundos) {
                    $inicio->modify("+{$minutosXPaciente} minutes 30 seconds");
                } else {
                    $inicio->modify("+{$minutosXPaciente} minutes");
                }
            }
        }

        return $slots;
    }

    /**
     * @param int[] $horariosAgenda
     */
    public static function estimateSlotCapacityFromHorarios(array $horariosAgenda, int $duracionMin): int
    {
        if (empty($horariosAgenda) || $duracionMin < 1) {
            return 0;
        }
        sort($horariosAgenda);
        $total = 0;
        $run = [$horariosAgenda[0]];
        for ($i = 1; $i < count($horariosAgenda); $i++) {
            if ($horariosAgenda[$i] === $run[count($run) - 1] + 1) {
                $run[] = $horariosAgenda[$i];
            } else {
                $total += static::minutesInHourRun($run) / $duracionMin;
                $run = [$horariosAgenda[$i]];
            }
        }
        $total += static::minutesInHourRun($run) / $duracionMin;

        return max(1, (int) floor($total));
    }

    private static function minutesInHourRun(array $run): int
    {
        if (empty($run)) {
            return 0;
        }
        $first = (int) $run[0];
        $last = (int) $run[count($run) - 1];

        return ($last - $first + 1) * 60;
    }

    /**
     * @param iterable<object> $agendas objetos con propiedades lunes_2..domingo_2
     */
    public static function validarGrupoSinSolapamientoEntreAgendas($agendas): bool
    {
        $arr_lunes = $arr_martes = $arr_miercoles = $arr_jueves = $arr_viernes = $arr_sabado = $arr_domingo = [];

        foreach ($agendas as $agenda) {
            if ($agenda->lunes_2 != '') {
                if (count(array_intersect($arr_lunes, explode(',', $agenda->lunes_2))) > 0) {
                    return false;
                }
                $arr_lunes = array_merge($arr_lunes, explode(',', $agenda->lunes_2));
            }
            if ($agenda->martes_2 != '') {
                if (count(array_intersect($arr_martes, explode(',', $agenda->martes_2))) > 0) {
                    return false;
                }
                $arr_martes = array_merge($arr_martes, explode(',', $agenda->martes_2));
            }
            if ($agenda->miercoles_2 != '') {
                if (count(array_intersect($arr_miercoles, explode(',', $agenda->miercoles_2))) > 0) {
                    return false;
                }
                $arr_miercoles = array_merge($arr_miercoles, explode(',', $agenda->miercoles_2));
            }
            if ($agenda->jueves_2 != '') {
                if (count(array_intersect($arr_jueves, explode(',', $agenda->jueves_2))) > 0) {
                    return false;
                }
                $arr_jueves = array_merge($arr_jueves, explode(',', $agenda->jueves_2));
            }
            if ($agenda->viernes_2 != '') {
                if (count(array_intersect($arr_viernes, explode(',', $agenda->viernes_2))) > 0) {
                    return false;
                }
                $arr_viernes = array_merge($arr_viernes, explode(',', $agenda->viernes_2));
            }
            if ($agenda->sabado_2 != '') {
                if (count(array_intersect($arr_sabado, explode(',', $agenda->sabado_2))) > 0) {
                    return false;
                }
                $arr_sabado = array_merge($arr_sabado, explode(',', $agenda->sabado_2));
            }
            if ($agenda->domingo_2 != '') {
                if (count(array_intersect($arr_domingo, explode(',', $agenda->domingo_2))) > 0) {
                    return false;
                }
                $arr_domingo = array_merge($arr_domingo, explode(',', $agenda->domingo_2));
            }
        }

        return true;
    }
}
