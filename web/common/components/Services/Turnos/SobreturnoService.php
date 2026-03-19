<?php

namespace common\components\Services\Turnos;

use Yii;
use common\models\Turno;
use common\models\TurnoNotificacionProgramada;
use common\models\EfectorTurnosConfig;
use common\models\TurnoEventoAudit;

class SobreturnoService
{
    /**
     * Tras crear un sobreturno, notifica a turnos pendientes posteriores el mismo día (mismo rrhh servicio).
     */
    public function notificarRetrasoPorSobreturno(Turno $sobreturno)
    {
        if (!$sobreturno->es_sobreturno) {
            return;
        }
        $cfg = EfectorTurnosConfig::getOrCreateForEfector((int) $sobreturno->id_efector);
        if (!$cfg->sobreturno_notificar_retraso) {
            return;
        }

        $minutos = max(5, (int) $cfg->sobreturno_minutos_retraso_estimado);
        $otros = Turno::findActive()
            ->where([
                'fecha' => $sobreturno->fecha,
                'id_rrhh_servicio_asignado' => $sobreturno->id_rrhh_servicio_asignado,
                'estado' => Turno::ESTADO_PENDIENTE,
            ])
            ->andWhere(['<>', 'id_turnos', $sobreturno->id_turnos])
            ->andWhere(['>', 'hora', $sobreturno->hora])
            ->orderBy(['hora' => SORT_ASC])
            ->all();

        $push = new PushNotificationSender();
        foreach ($otros as $t) {
            $runAt = date('Y-m-d H:i:s', strtotime('+' . (int) ($minutos / 2) . ' minutes'));
            $n = new TurnoNotificacionProgramada();
            $n->id_turno = (int) $t->id_turnos;
            $n->tipo = TurnoNotificacionProgramada::TIPO_RETRASO_SOBRETURNO;
            $n->run_at = $runAt;
            $n->estado = TurnoNotificacionProgramada::ESTADO_PENDIENTE;
            $n->payload_json = json_encode([
                'minutos_retraso_estimado' => $minutos,
                'id_sobreturno' => $sobreturno->id_turnos,
            ], JSON_UNESCAPED_UNICODE);
            $n->save(false);

            if ($t->persona) {
                $push->sendToPersona(
                    (int) $t->id_persona,
                    [
                        'type' => 'TURNO_RETRASO_SOBRETURNO',
                        'id_turno' => (string) $t->id_turnos,
                        'minutos' => (string) $minutos,
                    ],
                    'Posible demora en tu turno',
                    'Se agregó un sobreturno urgente; tu horario podría retrasarse aprox. ' . $minutos . ' min.'
                );
            }
        }

        TurnoEventoAudit::registrar($sobreturno->id_turnos, TurnoEventoAudit::TIPO_SOBRETURNO, Yii::$app->user->id ?? null, [
            'notificados' => count($otros),
        ]);
    }
}
