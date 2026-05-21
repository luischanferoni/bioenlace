<?php

namespace common\components\Scheduling\Service;

use common\components\Organization\Service\ProfesionalEfectorServicio\AgendaIntervaloMinutos;
use common\components\Organization\Service\ProfesionalEfectorServicio\AgendaSlotEngine;
use common\models\ProfesionalEfectorServicioAgendaVersion;
use common\models\Turno;
use common\models\TurnoResolucion;

/**
 * Normaliza fecha/hora PES desde slot_id y persiste intervalo, hora_fin e id_agenda_version.
 */
final class TurnoReservaSlotService
{
    /**
     * @return array{
     *   id_profesional_efector_servicio: int,
     *   fecha: string,
     *   hora: string,
     *   intervalo_minutos: int
     * }|null
     */
    public static function parseSlotId(string $slotId): ?array
    {
        $slotId = trim($slotId);
        if ($slotId === '') {
            return null;
        }
        $parts = explode('|', $slotId);
        if (count($parts) !== 3 && count($parts) !== 4) {
            return null;
        }
        $id0 = trim($parts[0]);
        $fecha = trim($parts[1]);
        $hora = trim($parts[2]);
        if ($fecha === '' || $hora === '') {
            return null;
        }
        $pesId = 0;
        if (stripos($id0, 'pes:') === 0) {
            $pesId = (int) trim(substr($id0, 4));
        } else {
            $pesId = (int) $id0;
        }
        if ($pesId <= 0) {
            return null;
        }
        $intervalo = AgendaIntervaloMinutos::DEFAULT;
        if (count($parts) === 4) {
            $intervalo = AgendaIntervaloMinutos::normalize((int) trim($parts[3]));
        }

        return [
            'id_profesional_efector_servicio' => $pesId,
            'fecha' => $fecha,
            'hora' => substr($hora, 0, 5),
            'intervalo_minutos' => $intervalo,
        ];
    }

    /**
     * Completa campos de reserva y valida grilla + disponibilidad.
     *
     * @throws \InvalidArgumentException
     */
    public static function aplicarCamposReserva(Turno $turno, ?int $excluirIdTurno = null): void
    {
        $idPes = (int) ($turno->id_profesional_efector_servicio ?? 0);
        $fecha = trim((string) ($turno->fecha ?? ''));
        $hora = trim((string) ($turno->hora ?? ''));
        if ($idPes <= 0 || $fecha === '' || $hora === '') {
            return;
        }

        $horaNorm = substr(TurnoResolucion::normalizarHora($hora), 0, 5);
        $version = ProfesionalEfectorServicioAgendaVersion::findVigenteParaPesEnFecha($idPes, $fecha);
        $intervalo = $version !== null
            ? $version->getIntervaloMinutosEfectivo()
            : AgendaIntervaloMinutos::DEFAULT;

        if (isset($turno->intervalo_minutos_reserva) && (int) $turno->intervalo_minutos_reserva > 0) {
            $intervalo = AgendaIntervaloMinutos::normalize((int) $turno->intervalo_minutos_reserva);
        }

        if ($version !== null) {
            if (!AgendaSlotEngine::horaEstaEnGrilla($version, $fecha, $horaNorm, $intervalo)) {
                throw new \InvalidArgumentException('El horario no corresponde a la grilla de la agenda.');
            }
            $turno->id_agenda_version = (int) $version->id;
        }

        $horaDb = $horaNorm . ':00';
        $fin = TurnoResolucion::sumarMinutos($horaDb, $intervalo);

        if (!TurnoSlotOccupancyService::estaDisponibleSlot($idPes, $fecha, $horaNorm, $excluirIdTurno)) {
            throw new \InvalidArgumentException('El horario ya no está disponible.');
        }

        $turno->hora = $horaDb;
        $turno->hora_fin = $fin;
        $turno->intervalo_minutos_reserva = $intervalo;
    }
}
