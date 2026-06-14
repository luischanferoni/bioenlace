<?php

namespace common\components\Domain\Scheduling\Service;

use common\components\Domain\Organization\Service\ProfesionalEfectorServicio\AgendaIntervaloMinutos;
use common\models\ProfesionalEfectorServicioAgendaVersion;
use common\models\Scheduling\Turno;
use common\models\TurnoResolucion;

/**
 * Ocupación por solapamiento de intervalo (no solo igualdad exacta de hora).
 */
final class TurnoSlotOccupancyService
{
    public static function haySolapamiento(
        int $idPes,
        string $fechaYmd,
        string $horaInicio,
        string $horaFin,
        ?int $excluirIdTurno = null
    ): bool {
        if ($idPes <= 0 || $fechaYmd === '') {
            return false;
        }
        $horaInicio = TurnoResolucion::normalizarHora($horaInicio);
        $horaFin = TurnoResolucion::normalizarHora($horaFin);
        if ($horaInicio === '' || $horaFin === '') {
            return false;
        }

        if (TurnoResolucion::existePendienteParaPesEnFranja($idPes, $fechaYmd, $horaInicio, $horaFin)) {
            return true;
        }

        $query = Turno::find()
            ->andWhere([
                'fecha' => $fechaYmd,
                'id_profesional_efector_servicio' => $idPes,
            ])
            ->andWhere(['in', 'estado', Turno::ESTADOS_PARA_DESHABILITAR]);
        if ($excluirIdTurno !== null && $excluirIdTurno > 0) {
            $query->andWhere(['<>', 'id_turnos', $excluirIdTurno]);
        }

        /** @var Turno[] $turnos */
        $turnos = $query->all();
        foreach ($turnos as $t) {
            $tIni = TurnoResolucion::normalizarHora((string) $t->hora);
            $tFin = $t->hora_fin !== null && trim((string) $t->hora_fin) !== ''
                ? TurnoResolucion::normalizarHora((string) $t->hora_fin)
                : TurnoResolucion::sumarMinutos(
                    $tIni,
                    self::intervaloDeTurno($t)
                );
            if (self::rangosSeSolapan($horaInicio, $horaFin, $tIni, $tFin)) {
                return true;
            }
        }

        return false;
    }

    public static function estaDisponibleSlot(
        int $idPes,
        string $fechaYmd,
        string $hora,
        ?int $excluirIdTurno = null
    ): bool {
        $version = ProfesionalEfectorServicioAgendaVersion::findVigenteParaPesEnFecha($idPes, $fechaYmd);
        $intervalo = $version !== null
            ? $version->getIntervaloMinutosEfectivo()
            : AgendaIntervaloMinutos::DEFAULT;
        $horaNorm = TurnoResolucion::normalizarHora($hora);
        $fin = TurnoResolucion::sumarMinutos($horaNorm, $intervalo);

        return !self::haySolapamiento($idPes, $fechaYmd, $horaNorm, $fin, $excluirIdTurno);
    }

    private static function intervaloDeTurno(Turno $t): int
    {
        if (isset($t->intervalo_minutos_reserva) && (int) $t->intervalo_minutos_reserva > 0) {
            return AgendaIntervaloMinutos::normalize((int) $t->intervalo_minutos_reserva);
        }
        $idPes = (int) ($t->id_profesional_efector_servicio ?? 0);
        if ($idPes > 0 && !empty($t->fecha)) {
            $v = ProfesionalEfectorServicioAgendaVersion::findVigenteParaPesEnFecha($idPes, (string) $t->fecha);
            if ($v !== null) {
                return $v->getIntervaloMinutosEfectivo();
            }
        }

        return AgendaIntervaloMinutos::DEFAULT;
    }

    private static function rangosSeSolapan(string $a1, string $a2, string $b1, string $b2): bool
    {
        return $a1 < $b2 && $b1 < $a2;
    }
}
