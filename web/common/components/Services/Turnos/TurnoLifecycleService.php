<?php

namespace common\components\Services\Turnos;

use Yii;
use yii\db\Expression;
use common\models\Turno;
use common\models\TurnoNotificacionProgramada;
use common\models\TurnoEventoAudit;

class TurnoLifecycleService
{
    /** @var TurnoConfirmationService */
    private $confirmation;

    public function __construct(TurnoConfirmationService $confirmation = null)
    {
        $this->confirmation = $confirmation ?: new TurnoConfirmationService();
    }

    public function afterTurnoCreado(Turno $turno)
    {
        $this->confirmation->ensureConfirmacionToken($turno);
        $this->confirmation->programarNotificaciones($turno);
        TurnoEventoAudit::registrar($turno->id_turnos, TurnoEventoAudit::TIPO_CREATE, Yii::$app->user->id ?? null);
    }

    /**
     * @param string $canal app|admin|telefono
     */
    public function cancelar(Turno $turno, $estadoMotivo, $canal = 'app', $idUser = null)
    {
        if ($turno->estado !== Turno::ESTADO_PENDIENTE) {
            throw new \InvalidArgumentException('Solo se pueden cancelar turnos pendientes');
        }

        if ($canal === 'app' && $estadoMotivo === Turno::ESTADO_MOTIVO_CANCELADO_PACIENTE) {
            $policy = new \common\components\Services\Turnos\TurnoCancellationPolicyService();
            if ($policy->autogestionBloqueada((int) $turno->id_persona, (int) $turno->id_efector)) {
                throw new PolicyModeradaException(
                    'Autogestión restringida: acercate al efector o llamá por teléfono para cancelar o reprogramar.'
                );
            }
        }

        $turno->estado = Turno::ESTADO_CANCELADO;
        $turno->estado_motivo = $estadoMotivo;
        $turno->deleted_by = $idUser ?: (Yii::$app->user->id ?? null);
        $turno->deleted_at = new Expression('NOW()');
        if (!$turno->save(false)) {
            return false;
        }

        TurnoNotificacionProgramada::cancelarPendientesPorTurno($turno->id_turnos);

        $tipo = $estadoMotivo === Turno::ESTADO_MOTIVO_CANCELADO_PACIENTE
            ? TurnoEventoAudit::TIPO_CANCEL_PAT
            : TurnoEventoAudit::TIPO_CANCEL_MED;
        TurnoEventoAudit::registrar($turno->id_turnos, $tipo, $idUser, ['canal' => $canal]);

        $push = new PushNotificationSender();
        if ($turno->persona && $estadoMotivo === Turno::ESTADO_MOTIVO_CANCELADO_MEDICO) {
            $push->sendToPersona(
                (int) $turno->id_persona,
                ['type' => 'TURNO_CANCELADO_EFECTOR', 'id_turno' => (string) $turno->id_turnos],
                'Turno cancelado por el consultorio',
                'Tu turno del ' . $turno->fecha . ' fue cancelado. Podés reprogramar desde la app.'
            );
        }

        return true;
    }
}
