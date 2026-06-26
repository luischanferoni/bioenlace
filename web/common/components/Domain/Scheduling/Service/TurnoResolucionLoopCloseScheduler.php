<?php

namespace common\components\Domain\Scheduling\Service;

use common\components\Platform\Core\Product\AutonomousAgentMetadata;
use common\models\Scheduling\Turno;
use common\models\TurnoNotificacionProgramada;
use Yii;

/**
 * Programa cierre de loop (A06) tras entrada en resolución.
 */
final class TurnoResolucionLoopCloseScheduler
{
    public const AGENT_ID = 'turno-resolucion-loop-close';

    public function scheduleAfterInitialPush(Turno $turno): void
    {
        if (!(Yii::$app->params['autonomous_agent_resolucion_loop_close_enabled'] ?? true)) {
            return;
        }

        $config = AutonomousAgentMetadata::loadAgent(self::AGENT_ID);
        if ($config === null) {
            return;
        }

        $hours = (int) ($config['loop_close_hours'] ?? 72);
        if ($hours <= 0) {
            return;
        }

        $this->cancelPendingLoopClose((int) $turno->id_turnos);

        $row = new TurnoNotificacionProgramada();
        $row->id_turno = (int) $turno->id_turnos;
        $row->tipo = TurnoNotificacionProgramada::TIPO_RESOLUCION_LOOP_CLOSE;
        $row->run_at = date('Y-m-d H:i:s', time() + $hours * 3600);
        $row->estado = TurnoNotificacionProgramada::ESTADO_PENDIENTE;
        $row->save(false);
    }

    public function cancelPendingLoopClose(int $idTurno): void
    {
        TurnoNotificacionProgramada::updateAll(
            ['estado' => TurnoNotificacionProgramada::ESTADO_CANCELADA],
            [
                'id_turno' => $idTurno,
                'tipo' => TurnoNotificacionProgramada::TIPO_RESOLUCION_LOOP_CLOSE,
                'estado' => TurnoNotificacionProgramada::ESTADO_PENDIENTE,
            ]
        );
    }
}
