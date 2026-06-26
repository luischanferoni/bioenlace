<?php

namespace console\controllers;

use Yii;
use yii\console\Controller;
use common\models\TurnoNotificacionProgramada;
use common\models\Turno;
use common\components\Platform\Core\Service\Push\PushNotificationSender;
use common\components\Platform\Core\Service\Push\FcmPushConfig;
use common\components\Domain\Scheduling\Service\TurnoReminderContentBuilder;
use common\components\Domain\Clinical\Service\AppointmentReasonBatchService;

/**
 * Procesa turno_notificacion_programada (cron cada N minutos).
 */
class TurnoNotificacionController extends Controller
{
    public function actionRun($limit = 50)
    {
        $limit = (int) $limit;
        $q = TurnoNotificacionProgramada::find()
            ->where(['estado' => TurnoNotificacionProgramada::ESTADO_PENDIENTE])
            ->andWhere(['<=', 'run_at', date('Y-m-d H:i:s')])
            ->orderBy(['run_at' => SORT_ASC])
            ->limit($limit);

        $push = new PushNotificationSender();
        $builder = new TurnoReminderContentBuilder();
        $n = 0;
        foreach ($q->each() as $row) {
            /** @var TurnoNotificacionProgramada $row */
            $turno = Turno::findActive()->andWhere(['id_turnos' => $row->id_turno])->one();
            if (!$turno || $turno->estado !== Turno::ESTADO_PENDIENTE) {
                $row->estado = TurnoNotificacionProgramada::ESTADO_CANCELADA;
                $row->save(false);
                continue;
            }
            try {
                if ($row->tipo === TurnoNotificacionProgramada::TIPO_REMINDER
                    || $row->tipo === TurnoNotificacionProgramada::TIPO_TRANSPORT_HINT) {
                    $content = $builder->buildForTurno($turno);
                    $content['data']['type'] = $row->tipo === TurnoNotificacionProgramada::TIPO_TRANSPORT_HINT
                        ? 'TURNO_TRANSPORT_HINT' : 'TURNO_REMINDER';
                    if ($turno->paciente) {
                        $push->sendToPersona((int) $turno->id_persona, $content['data'], $content['title'], $content['body']);
                    }
                } elseif ($row->tipo === TurnoNotificacionProgramada::TIPO_CONFIRM_REQUEST) {
                    $token = (new \common\components\Domain\Scheduling\Service\TurnoConfirmationService())->ensureConfirmacionToken($turno);
                    $push->sendToPersona(
                        (int) $turno->id_persona,
                        [
                            'type' => 'TURNO_CONFIRMAR',
                            'id_turno' => (string) $turno->id_turnos,
                            'token' => $token,
                        ],
                        'Confirmá tu turno',
                        'Confirmá asistencia al turno del ' . $turno->fecha . ' ' . $turno->hora
                    );
                } elseif ($row->tipo === TurnoNotificacionProgramada::TIPO_MOTIVOS_IA_BATCH) {
                    $meta = $row->payload_json ? json_decode($row->payload_json, true) : [];
                    $encounterId = is_array($meta) ? (int) ($meta['encounter_id'] ?? 0) : 0;
                    if ($encounterId > 0) {
                        AppointmentReasonBatchService::process($encounterId);
                    }
                } elseif ($row->tipo === TurnoNotificacionProgramada::TIPO_RETRASO_SOBRETURNO) {
                    $meta = $row->payload_json ? json_decode($row->payload_json, true) : [];
                    $min = isset($meta['minutos_retraso_estimado']) ? (int) $meta['minutos_retraso_estimado'] : 30;
                    if ($turno->paciente) {
                        $push->sendToPersona(
                            (int) $turno->id_persona,
                            ['type' => 'TURNO_RETRASO_SOBRETURNO', 'id_turno' => (string) $turno->id_turnos],
                            'Demora posible',
                            'Tu turno podría demorar ~' . $min . ' min por un sobreturno.'
                        );
                    }
                } elseif ($row->tipo === TurnoNotificacionProgramada::TIPO_RESOLUCION_MULTICANAL) {
                    $result = (new \common\components\Domain\Scheduling\Service\TurnoResolucionMulticanalAgent())
                        ->processScheduled($row, $turno);
                    if ($result === 'cancelled') {
                        $row->estado = TurnoNotificacionProgramada::ESTADO_CANCELADA;
                        $row->save(false);
                        continue;
                    }
                    if ($result === 'deferred') {
                        continue;
                    }
                } elseif ($row->tipo === TurnoNotificacionProgramada::TIPO_RESOLUCION_LOOP_CLOSE) {
                    $result = (new \common\components\Domain\Scheduling\Service\TurnoResolucionLoopCloseAgent())
                        ->processScheduled($row, $turno);
                    if ($result === 'cancelled') {
                        $row->estado = TurnoNotificacionProgramada::ESTADO_CANCELADA;
                        $row->save(false);
                        continue;
                    }
                } elseif ($row->tipo === TurnoNotificacionProgramada::TIPO_ANTINOSHOW_CHECKPOINT) {
                    $result = (new \common\components\Domain\Scheduling\Service\TurnoAntinoshowAgent())
                        ->processCheckpoint($row, $turno);
                    if ($result === 'cancelled') {
                        $row->estado = TurnoNotificacionProgramada::ESTADO_CANCELADA;
                        $row->save(false);
                        continue;
                    }
                } elseif ($row->tipo === TurnoNotificacionProgramada::TIPO_ANTINOSHOW_RELEASE) {
                    $result = (new \common\components\Domain\Scheduling\Service\TurnoAntinoshowAgent())
                        ->processRelease($row, $turno);
                    if ($result === 'cancelled') {
                        $row->estado = TurnoNotificacionProgramada::ESTADO_CANCELADA;
                        $row->save(false);
                        continue;
                    }
                }
                $row->estado = TurnoNotificacionProgramada::ESTADO_ENVIADA;
                $row->save(false);
                $n++;
            } catch (\Throwable $e) {
                $row->intentos = (int) $row->intentos + 1;
                $row->ultimo_error = $e->getMessage();
                if ($row->intentos >= 5) {
                    $row->estado = TurnoNotificacionProgramada::ESTADO_FALLIDA;
                }
                $row->save(false);
                Yii::error('TurnoNotificacion: ' . $e->getMessage(), FcmPushConfig::LOG_CATEGORY);
            }
        }
        $this->stdout("Procesadas: $n\n");
    }
}
