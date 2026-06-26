<?php

namespace common\components\Domain\Scheduling\Service;

use common\components\Platform\Core\Product\AutonomousAgentMetadata;
use common\models\Scheduling\Turno;
use common\models\TurnoNotificacionProgramada;
use Yii;

/**
 * Programa checkpoints anti no-show (A04).
 */
final class TurnoAntinoshowScheduler
{
    public const AGENT_ID = 'turno-antinoshow';

    public function scheduleForTurno(Turno $turno, int $turnoTimestamp): void
    {
        if (!(Yii::$app->params['autonomous_agent_antinoshow_enabled'] ?? true)) {
            return;
        }

        $config = AutonomousAgentMetadata::loadAgent(self::AGENT_ID);
        if ($config === null) {
            return;
        }

        $this->cancelPendingAntinoshow((int) $turno->id_turnos);

        $checkpoints = is_array($config['checkpoints'] ?? null) ? $config['checkpoints'] : [];
        foreach ($checkpoints as $checkpoint) {
            if (!is_array($checkpoint)) {
                continue;
            }
            $hoursBefore = (int) ($checkpoint['hours_before'] ?? 0);
            if ($hoursBefore <= 0) {
                continue;
            }
            $runAt = $turnoTimestamp - $hoursBefore * 3600;
            if ($runAt <= time()) {
                continue;
            }
            $this->insertProgramada(
                $turno,
                TurnoNotificacionProgramada::TIPO_ANTINOSHOW_CHECKPOINT,
                date('Y-m-d H:i:s', $runAt),
                ['hours_before' => $hoursBefore]
            );
        }
    }

    public function scheduleRelease(Turno $turno, int $hoursBeforeAppointment): void
    {
        $dt = strtotime((string) $turno->fecha . ' ' . substr((string) $turno->hora, 0, 5) . ':00');
        if ($dt === false) {
            return;
        }

        $runAt = $dt - max(1, $hoursBeforeAppointment) * 3600;
        if ($runAt <= time()) {
            return;
        }

        $this->cancelPendingRelease((int) $turno->id_turnos);
        $this->insertProgramada(
            $turno,
            TurnoNotificacionProgramada::TIPO_ANTINOSHOW_RELEASE,
            date('Y-m-d H:i:s', $runAt),
            ['hours_before_appointment' => $hoursBeforeAppointment]
        );
    }

    public function cancelPendingAntinoshow(int $idTurno): void
    {
        TurnoNotificacionProgramada::updateAll(
            ['estado' => TurnoNotificacionProgramada::ESTADO_CANCELADA],
            [
                'id_turno' => $idTurno,
                'estado' => TurnoNotificacionProgramada::ESTADO_PENDIENTE,
                'tipo' => [
                    TurnoNotificacionProgramada::TIPO_ANTINOSHOW_CHECKPOINT,
                    TurnoNotificacionProgramada::TIPO_ANTINOSHOW_RELEASE,
                ],
            ]
        );
    }

    private function cancelPendingRelease(int $idTurno): void
    {
        TurnoNotificacionProgramada::updateAll(
            ['estado' => TurnoNotificacionProgramada::ESTADO_CANCELADA],
            [
                'id_turno' => $idTurno,
                'tipo' => TurnoNotificacionProgramada::TIPO_ANTINOSHOW_RELEASE,
                'estado' => TurnoNotificacionProgramada::ESTADO_PENDIENTE,
            ]
        );
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function insertProgramada(Turno $turno, string $tipo, string $runAt, array $payload): void
    {
        $row = new TurnoNotificacionProgramada();
        $row->id_turno = (int) $turno->id_turnos;
        $row->tipo = $tipo;
        $row->run_at = $runAt;
        $row->estado = TurnoNotificacionProgramada::ESTADO_PENDIENTE;
        $row->payload_json = json_encode($payload, JSON_UNESCAPED_UNICODE);
        $row->save(false);
    }
}
