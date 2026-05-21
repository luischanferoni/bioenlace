<?php

namespace common\components\Scheduling\Service;

use Yii;
use yii\db\Expression;
use common\models\Turno;
use common\models\TurnoNotificacionProgramada;
use common\models\TurnoEventoAudit;
use common\models\TurnoResolucion;

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
     * @param array<string, mixed> $metaAudit opcional; se fusiona en {@see TurnoEventoAudit::registrar()} (p. ej. razon_cancelacion).
     */
    public function cancelar(
        Turno $turno,
        $estadoMotivo,
        $canal = 'app',
        $idUser = null,
        array $metaAudit = [],
        bool $notificarPacientePush = true
    ) {
        if (
            $turno->estado !== Turno::ESTADO_PENDIENTE
            && $turno->estado !== Turno::ESTADO_EN_RESOLUCION
        ) {
            throw new \InvalidArgumentException('Solo se pueden cancelar turnos pendientes o en resolución');
        }

        if (
            $canal === 'app'
            && $estadoMotivo === Turno::ESTADO_MOTIVO_CANCELADO_PACIENTE
            && $turno->estado !== Turno::ESTADO_EN_RESOLUCION
        ) {
            $policy = new \common\components\Scheduling\Service\TurnoCancellationPolicyService();
            if ($policy->autogestionBloqueada((int) $turno->id_persona, (int) $turno->id_efector)) {
                throw new PolicyModeradaException(
                    'Autogestión restringida: acercate al efector o llamá por teléfono para cancelar o reprogramar.'
                );
            }
            (new \common\components\Scheduling\Service\TurnoAutogestionAnticipacionService())->assertPuedeCancelarPorApp($turno);
        }

        $turno->estado = Turno::ESTADO_CANCELADO;
        $turno->estado_motivo = $estadoMotivo;
        $turno->deleted_by = $idUser ?: (Yii::$app->user->id ?? null);
        $turno->deleted_at = new Expression('NOW()');
        if (!$turno->save(false)) {
            return false;
        }

        TurnoNotificacionProgramada::cancelarPendientesPorTurno($turno->id_turnos);

        $resPend = TurnoResolucion::findPendientePorTurno((int) $turno->id_turnos);
        if ($resPend !== null) {
            $resPend->estado = TurnoResolucion::ESTADO_CANCELADO;
            $resPend->save(false);
        }

        $tipo = $estadoMotivo === Turno::ESTADO_MOTIVO_CANCELADO_PACIENTE
            ? TurnoEventoAudit::TIPO_CANCEL_PAC
            : TurnoEventoAudit::TIPO_CANCEL_MED;
        $meta = array_merge($metaAudit, ['canal' => $canal]);
        TurnoEventoAudit::registrar($turno->id_turnos, $tipo, $idUser, $meta);

        if ($notificarPacientePush && $turno->paciente && $estadoMotivo === Turno::ESTADO_MOTIVO_CANCELADO_MEDICO) {
            $push = new PushNotificationSender();
            $push->sendToPersona(
                (int) $turno->id_persona,
                ['type' => 'TURNO_CANCELADO_EFECTOR', 'id_turno' => (string) $turno->id_turnos],
                'Turno cancelado por el consultorio',
                'Tu turno del ' . $turno->fecha . ' fue cancelado.'
            );
        }

        return true;
    }
}
