<?php

namespace common\components\Domain\Scheduling\Service;

use common\components\Domain\Scheduling\Service\BehaviorProfile\TurnoCanonicalEventCommand;
use common\components\Domain\Scheduling\Service\BehaviorProfile\TurnoCanonicalEventService;
use common\components\Platform\Core\Service\Push\PushNotificationSender;
use Yii;
use yii\db\Expression;
use common\models\Scheduling\Turno;
use common\models\TurnoNotificacionProgramada;
use common\models\TurnoEventoAudit;
use common\models\TurnoResolucion;

class TurnoLifecycleService
{
    /** @var TurnoConfirmationService */
    private $confirmation;

    /** @var TurnoCanonicalEventService */
    private $canonicalEvents;

    public function __construct(
        TurnoConfirmationService $confirmation = null,
        ?TurnoCanonicalEventService $canonicalEvents = null
    ) {
        $this->confirmation = $confirmation ?: new TurnoConfirmationService();
        $this->canonicalEvents = $canonicalEvents ?: new TurnoCanonicalEventService();
    }

    /**
     * @param string|null $actorType {@see TurnoEventoAudit::actorTypeValues()}; null → inferencia segura
     * @param string|null $canal
     */
    public function afterTurnoCreado(Turno $turno, ?string $actorType = null, ?string $canal = null)
    {
        $this->confirmation->ensureConfirmacionToken($turno);
        $this->confirmation->programarNotificaciones($turno);

        $idUser = Yii::$app->user->id ?? null;
        $actor = $actorType ?: $this->inferActorForCreate($canal, $idUser !== null ? (int) $idUser : null);
        $this->canonicalEvents->record(TurnoCanonicalEventCommand::create(
            (int) $turno->id_turnos,
            (int) $turno->id_persona,
            TurnoEventoAudit::EVENT_APPOINTMENT_CREATED,
            $actor,
            'native:' . TurnoEventoAudit::EVENT_APPOINTMENT_CREATED . ':' . (int) $turno->id_turnos,
            TurnoEventoAudit::QUALITY_NATIVE,
            $idUser !== null ? (int) $idUser : null,
            $canal,
            'lifecycle',
            null,
            null,
            $canal !== null ? ['canal' => $canal] : [],
            TurnoEventoAudit::TIPO_CREATE
        ));
    }

    /**
     * @param string $canal app|admin|telefono|sistema|…
     * @param array<string, mixed> $metaAudit opcional; se fusiona en meta del evento (p. ej. razon_cancelacion).
     * @param string|null $actorType Si null, se deriva de estado_motivo / canal sin atribuir al paciente por defecto.
     */
    public function cancelar(
        Turno $turno,
        $estadoMotivo,
        $canal = 'app',
        $idUser = null,
        array $metaAudit = [],
        bool $notificarPacientePush = true,
        ?string $actorType = null
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
            $policy = new \common\components\Domain\Scheduling\Service\TurnoCancellationPolicyService();
            if ($policy->autogestionBloqueada((int) $turno->id_persona, (int) $turno->id_efector)) {
                throw new PolicyModeradaException(
                    'Autogestión restringida: acercate al efector o llamá por teléfono para cancelar o reprogramar.'
                );
            }
            (new \common\components\Domain\Scheduling\Service\TurnoAutogestionAnticipacionService())->assertPuedeCancelarPorApp($turno);
        }

        $tx = Turno::getDb()->beginTransaction();
        try {
            $turno->estado = Turno::ESTADO_CANCELADO;
            $turno->estado_motivo = $estadoMotivo;
            $turno->deleted_by = $idUser ?: (Yii::$app->user->id ?? null);
            $turno->deleted_at = new Expression('NOW()');
            if (!$turno->save(false)) {
                $tx->rollBack();
                return false;
            }

            TurnoNotificacionProgramada::cancelarPendientesPorTurno($turno->id_turnos);

            $resPend = TurnoResolucion::findPendientePorTurno((int) $turno->id_turnos);
            if ($resPend !== null) {
                $resPend->estado = TurnoResolucion::ESTADO_CANCELADO;
                $resPend->save(false);
            }

            $actor = $actorType ?: $this->inferActorForCancel((string) $estadoMotivo, (string) $canal, $metaAudit);
            $motivoNorm = isset($metaAudit['razon_cancelacion'])
                ? (string) $metaAudit['razon_cancelacion']
                : ((string) $estadoMotivo !== '' ? (string) $estadoMotivo : null);
            $meta = array_merge($metaAudit, [
                'canal' => $canal,
                'estado_motivo' => $estadoMotivo,
                'actor_type' => $actor,
            ]);
            $legacyTipo = $actor === TurnoEventoAudit::ACTOR_PACIENTE
                || $actor === TurnoEventoAudit::ACTOR_REPRESENTANTE
                ? TurnoEventoAudit::TIPO_CANCEL_PAC
                : TurnoEventoAudit::TIPO_CANCEL_MED;

            $this->canonicalEvents->record(TurnoCanonicalEventCommand::create(
                (int) $turno->id_turnos,
                (int) $turno->id_persona,
                TurnoEventoAudit::EVENT_APPOINTMENT_CANCELLED,
                $actor,
                'native:' . TurnoEventoAudit::EVENT_APPOINTMENT_CANCELLED . ':' . (int) $turno->id_turnos . ':' . (string) $estadoMotivo,
                TurnoEventoAudit::QUALITY_NATIVE,
                $idUser !== null ? (int) $idUser : null,
                (string) $canal,
                'lifecycle',
                $motivoNorm,
                null,
                $meta,
                $legacyTipo
            ));
            $tx->commit();
        } catch (\Throwable $e) {
            if ($tx->isActive) {
                $tx->rollBack();
            }
            throw $e;
        }

        if ($notificarPacientePush && $turno->paciente && (
            $estadoMotivo === Turno::ESTADO_MOTIVO_CANCELADO_MEDICO
            || $estadoMotivo === Turno::ESTADO_MOTIVO_CANCELADO_EFECTOR
        )) {
            $push = new PushNotificationSender();
            $push->sendToPersona(
                (int) $turno->id_persona,
                ['type' => 'TURNO_CANCELADO_EFECTOR', 'id_turno' => (string) $turno->id_turnos],
                'Turno cancelado por el consultorio',
                'Tu turno del ' . $turno->fecha . ' fue cancelado.'
            );
        }

        try {
            (new \common\components\Domain\Scheduling\Service\TurnoWaitlistFillAgent())->onTurnoCancelled($turno);
        } catch (\Throwable $e) {
            Yii::warning('Waitlist fill: ' . $e->getMessage(), 'turno-waitlist');
        }

        \common\components\Domain\Integrations\Scheduling\Service\TurnoFhirOutboundNotifier::afterEstadoChanged($turno);

        return true;
    }

    public function registrarNoShow(Turno $turno, ?int $idUser = null): void
    {
        $tx = Turno::getDb()->beginTransaction();
        try {
            $turno->atendido = Turno::ATENDIDO_NO;
            $turno->estado = Turno::ESTADO_SIN_ATENDER;
            $turno->estado_motivo = Turno::ESTADO_MOTIVO_SIN_ATENDER_PACIENTE;
            if (!$turno->save(false)) {
                throw new \RuntimeException('No se pudo registrar la inasistencia');
            }
            $this->canonicalEvents->record(TurnoCanonicalEventCommand::create(
                (int) $turno->id_turnos,
                (int) $turno->id_persona,
                TurnoEventoAudit::EVENT_NO_SHOW_RECORDED,
                TurnoEventoAudit::ACTOR_PACIENTE,
                'native:' . TurnoEventoAudit::EVENT_NO_SHOW_RECORDED . ':' . (int) $turno->id_turnos,
                TurnoEventoAudit::QUALITY_NATIVE,
                $idUser,
                'staff',
                'lifecycle',
                Turno::ESTADO_MOTIVO_SIN_ATENDER_PACIENTE,
                null,
                [],
                TurnoEventoAudit::TIPO_NO_SHOW
            ));
            $tx->commit();
        } catch (\Throwable $e) {
            if ($tx->isActive) {
                $tx->rollBack();
            }
            throw $e;
        }
        \common\components\Domain\Integrations\Scheduling\Service\TurnoFhirOutboundNotifier::afterEstadoChanged($turno);
    }

    public function marcarAtendido(Turno $turno, ?int $idUser = null): void
    {
        $tx = Turno::getDb()->beginTransaction();
        try {
            $turno->atendido = Turno::ATENDIDO_SI;
            $turno->estado = Turno::ESTADO_ATENDIDO;
            $turno->estado_motivo = null;
            if (!$turno->save(false)) {
                throw new \RuntimeException('No se pudo marcar el turno como atendido');
            }
            $this->canonicalEvents->record(TurnoCanonicalEventCommand::create(
                (int) $turno->id_turnos,
                (int) $turno->id_persona,
                TurnoEventoAudit::EVENT_ATTENDED,
                TurnoEventoAudit::ACTOR_STAFF,
                'native:' . TurnoEventoAudit::EVENT_ATTENDED . ':' . (int) $turno->id_turnos,
                TurnoEventoAudit::QUALITY_NATIVE,
                $idUser,
                'staff',
                'lifecycle'
            ));
            $tx->commit();
        } catch (\Throwable $e) {
            if ($tx->isActive) {
                $tx->rollBack();
            }
            throw $e;
        }
        \common\components\Domain\Integrations\Scheduling\Service\TurnoFhirOutboundNotifier::afterEstadoChanged($turno);
    }

    public function corregirNoShow(
        Turno $turno,
        int $correctedEventId,
        string $replacementOutcome,
        ?int $idUser = null
    ): void {
        $allowed = ['ATTENDED', 'PENDING', 'UNKNOWN'];
        if (!in_array($replacementOutcome, $allowed, true)) {
            throw new \InvalidArgumentException('replacement_outcome inválido');
        }

        $tx = Turno::getDb()->beginTransaction();
        try {
            if ($replacementOutcome === 'ATTENDED') {
                $turno->estado = Turno::ESTADO_ATENDIDO;
                $turno->atendido = Turno::ATENDIDO_SI;
                $turno->estado_motivo = null;
            } elseif ($replacementOutcome === 'PENDING') {
                $turno->estado = Turno::ESTADO_PENDIENTE;
                $turno->atendido = null;
                $turno->estado_motivo = null;
            }
            if (!$turno->save(false)) {
                throw new \RuntimeException('No se pudo aplicar la corrección del no-show');
            }

            $this->canonicalEvents->record(TurnoCanonicalEventCommand::create(
                (int) $turno->id_turnos,
                (int) $turno->id_persona,
                TurnoEventoAudit::EVENT_NO_SHOW_CORRECTED,
                TurnoEventoAudit::ACTOR_STAFF,
                'native:' . TurnoEventoAudit::EVENT_NO_SHOW_CORRECTED . ':' . $correctedEventId . ':' . $replacementOutcome,
                TurnoEventoAudit::QUALITY_NATIVE,
                $idUser,
                'staff',
                'lifecycle',
                'CORRECCION_NO_SHOW',
                null,
                ['replacement_outcome' => $replacementOutcome],
                null,
                $correctedEventId
            ));
            if ($replacementOutcome === 'ATTENDED') {
                $this->canonicalEvents->record(TurnoCanonicalEventCommand::create(
                    (int) $turno->id_turnos,
                    (int) $turno->id_persona,
                    TurnoEventoAudit::EVENT_ATTENDED,
                    TurnoEventoAudit::ACTOR_STAFF,
                    'native:' . TurnoEventoAudit::EVENT_ATTENDED . ':' . (int) $turno->id_turnos,
                    TurnoEventoAudit::QUALITY_NATIVE,
                    $idUser,
                    'staff',
                    'lifecycle'
                ));
            }
            $tx->commit();
        } catch (\Throwable $e) {
            if ($tx->isActive) {
                $tx->rollBack();
            }
            throw $e;
        }
        \common\components\Domain\Integrations\Scheduling\Service\TurnoFhirOutboundNotifier::afterEstadoChanged($turno);
    }

    /**
     * Persiste un turno ya mutado y conserva snapshots anterior/nuevo en el evento.
     *
     * @param array<string, mixed> $before
     */
    public function reprogramar(
        Turno $turno,
        array $before,
        string $actorType,
        string $channel,
        ?int $idUser = null
    ): void {
        $after = self::scheduleSnapshot($turno);
        $fingerprint = hash('sha256', json_encode([$before, $after], JSON_UNESCAPED_UNICODE) ?: '');
        $tx = Turno::getDb()->beginTransaction();
        try {
            if (!$turno->save()) {
                throw new \InvalidArgumentException(implode(', ', $turno->getErrorSummary(true)));
            }
            $this->canonicalEvents->record(TurnoCanonicalEventCommand::create(
                (int) $turno->id_turnos,
                (int) $turno->id_persona,
                TurnoEventoAudit::EVENT_APPOINTMENT_RESCHEDULED,
                $actorType,
                'native:' . TurnoEventoAudit::EVENT_APPOINTMENT_RESCHEDULED . ':'
                    . (int) $turno->id_turnos . ':' . $fingerprint,
                TurnoEventoAudit::QUALITY_NATIVE,
                $idUser,
                $channel,
                'lifecycle',
                null,
                null,
                ['before' => $before, 'after' => $after]
            ));
            $tx->commit();
        } catch (\Throwable $e) {
            if ($tx->isActive) {
                $tx->rollBack();
            }
            throw $e;
        }
        \common\components\Domain\Integrations\Scheduling\Service\TurnoFhirOutboundNotifier::afterEstadoChanged($turno);
    }

    public function entrarEnResolucion(
        Turno $turno,
        string $actorType = TurnoEventoAudit::ACTOR_SISTEMA,
        ?int $idUser = null
    ): void {
        $tx = Turno::getDb()->beginTransaction();
        try {
            $turno->estado = Turno::ESTADO_EN_RESOLUCION;
            if (!$turno->save(false)) {
                throw new \RuntimeException('No se pudo marcar el turno en resolución');
            }
            $this->canonicalEvents->record(TurnoCanonicalEventCommand::create(
                (int) $turno->id_turnos,
                (int) $turno->id_persona,
                TurnoEventoAudit::EVENT_APPOINTMENT_ENTERED_RESOLUTION,
                $actorType,
                'native:' . TurnoEventoAudit::EVENT_APPOINTMENT_ENTERED_RESOLUTION . ':'
                    . (int) $turno->id_turnos,
                TurnoEventoAudit::QUALITY_NATIVE,
                $idUser,
                $actorType === TurnoEventoAudit::ACTOR_SISTEMA ? 'sistema' : 'staff',
                'lifecycle'
            ));
            $tx->commit();
        } catch (\Throwable $e) {
            if ($tx->isActive) {
                $tx->rollBack();
            }
            throw $e;
        }
    }

    /**
     * @return array<string, mixed>
     */
    public static function scheduleSnapshot(Turno $turno): array
    {
        return [
            'fecha' => (string) $turno->fecha,
            'hora' => (string) $turno->hora,
            'id_efector' => (int) ($turno->id_efector ?? 0) ?: null,
            'id_servicio' => (int) ($turno->id_servicio_asignado ?? 0) ?: null,
            'id_profesional_efector_servicio' =>
                (int) ($turno->id_profesional_efector_servicio ?? 0) ?: null,
            'modalidad' => (string) ($turno->tipo_atencion ?? ''),
        ];
    }

    private function inferActorForCreate(?string $canal, ?int $idUser): string
    {
        if ($canal === 'sistema') {
            return TurnoEventoAudit::ACTOR_SISTEMA;
        }
        if ($canal === 'app' || $canal === 'paciente') {
            return TurnoEventoAudit::ACTOR_PACIENTE;
        }
        if ($idUser !== null && $idUser > 0) {
            return TurnoEventoAudit::ACTOR_STAFF;
        }

        return TurnoEventoAudit::ACTOR_SISTEMA;
    }

    /**
     * @param array<string, mixed> $metaAudit
     */
    private function inferActorForCancel(string $estadoMotivo, string $canal, array $metaAudit): string
    {
        if (isset($metaAudit['actor_type'])
            && in_array((string) $metaAudit['actor_type'], TurnoEventoAudit::actorTypeValues(), true)
        ) {
            return (string) $metaAudit['actor_type'];
        }
        switch ($estadoMotivo) {
            case Turno::ESTADO_MOTIVO_CANCELADO_PACIENTE:
                return TurnoEventoAudit::ACTOR_PACIENTE;
            case Turno::ESTADO_MOTIVO_CANCELADO_SISTEMA:
                return TurnoEventoAudit::ACTOR_SISTEMA;
            case Turno::ESTADO_MOTIVO_CANCELADO_EFECTOR:
                return TurnoEventoAudit::ACTOR_EFECTOR;
            case Turno::ESTADO_MOTIVO_CANCELADO_MEDICO:
                return TurnoEventoAudit::ACTOR_STAFF;
            default:
                if ($canal === 'sistema') {
                    return TurnoEventoAudit::ACTOR_SISTEMA;
                }

                return TurnoEventoAudit::ACTOR_STAFF;
        }
    }
}
