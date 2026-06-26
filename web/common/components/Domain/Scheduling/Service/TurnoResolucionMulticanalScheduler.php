<?php

namespace common\components\Domain\Scheduling\Service;

use common\components\Platform\Core\Product\AutonomousAgentMetadata;
use common\models\Scheduling\Turno;
use common\models\TurnoNotificacionProgramada;
use Yii;

/**
 * Programa escaladas multicanal tras el push inicial de reubicación.
 */
final class TurnoResolucionMulticanalScheduler
{
    public const AGENT_ID = 'turno-resolucion-multicanal';

    public function scheduleAfterInitialPush(Turno $turno): void
    {
        if (!(Yii::$app->params['autonomous_agent_resolucion_multicanal_enabled'] ?? true)) {
            return;
        }

        $config = AutonomousAgentMetadata::loadAgent(self::AGENT_ID);
        if ($config === null) {
            return;
        }

        $hours = (int) ($config['escalation_hours_after_push'] ?? 24);
        if ($hours <= 0) {
            return;
        }

        $this->cancelPendingMulticanal((int) $turno->id_turnos);

        $runAt = date('Y-m-d H:i:s', time() + $hours * 3600);
        $this->insertProgramada($turno, 1, $runAt);
    }

    public function scheduleNextChannel(Turno $turno, int $channelIndex, ?array $config = null): void
    {
        $config = $config ?? AutonomousAgentMetadata::loadAgent(self::AGENT_ID) ?? [];
        $hours = (int) ($config['hours_between_channels'] ?? 12);
        if ($hours <= 0) {
            $hours = 12;
        }

        $runAt = $this->adjustToLegalWindow(
            time() + $hours * 3600,
            $config
        );
        $this->insertProgramada($turno, $channelIndex, date('Y-m-d H:i:s', $runAt));
    }

    /**
     * @param array<string, mixed> $config
     */
    public function adjustToLegalWindow(int $timestamp, array $config): int
    {
        $start = (string) ($config['legal_hour_start'] ?? '09:00');
        $end = (string) ($config['legal_hour_end'] ?? '21:00');
        $dt = (new \DateTimeImmutable())->setTimestamp($timestamp);
        $h = (int) $dt->format('H');
        $startH = (int) substr($start, 0, 2);
        $endH = (int) substr($end, 0, 2);

        if ($h < $startH) {
            return $dt->setTime($startH, 0)->getTimestamp();
        }
        if ($h >= $endH) {
            return $dt->modify('+1 day')->setTime($startH, 0)->getTimestamp();
        }

        return $timestamp;
    }

    private function cancelPendingMulticanal(int $idTurno): void
    {
        TurnoNotificacionProgramada::updateAll(
            ['estado' => TurnoNotificacionProgramada::ESTADO_CANCELADA],
            [
                'id_turno' => $idTurno,
                'tipo' => TurnoNotificacionProgramada::TIPO_RESOLUCION_MULTICANAL,
                'estado' => TurnoNotificacionProgramada::ESTADO_PENDIENTE,
            ]
        );
    }

    private function insertProgramada(Turno $turno, int $channelIndex, string $runAt): void
    {
        $row = new TurnoNotificacionProgramada();
        $row->id_turno = (int) $turno->id_turnos;
        $row->tipo = TurnoNotificacionProgramada::TIPO_RESOLUCION_MULTICANAL;
        $row->run_at = $runAt;
        $row->estado = TurnoNotificacionProgramada::ESTADO_PENDIENTE;
        $row->payload_json = json_encode(['channel_index' => $channelIndex], JSON_UNESCAPED_UNICODE);
        $row->save(false);
    }
}
