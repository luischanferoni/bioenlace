<?php

namespace common\components\Domain\Scheduling\Service;

use common\components\Domain\Scheduling\Service\BehaviorProfile\TurnoAdvancePushReceiptProjector;
use common\components\Domain\Scheduling\Service\BehaviorProfile\TurnoCanonicalEventCommand;
use common\components\Domain\Scheduling\Service\BehaviorProfile\TurnoCanonicalEventService;
use common\components\Platform\Agent\AgentRunRecorder;
use common\components\Platform\Core\Product\AutonomousAgentMetadata;
use common\components\Platform\Core\Service\Push\PushNotificationSender;
use common\components\Platform\Core\Service\Push\PushNotificationTypes;
use common\models\Scheduling\Turno;
use common\models\Scheduling\TurnoAdvanceCampaign;
use common\models\Scheduling\TurnoAdvanceOffer;
use common\models\TurnoEventoAudit;
use common\models\TurnoResolucion;
use Yii;
use yii\db\Expression;

/**
 * Agente de ofertas secuenciales de adelantamiento ante cancelación.
 * El slot permanece público; no hay hold.
 */
final class TurnoAdvanceOfferAgent
{
    public const AGENT_ID = 'turno-advance-offer';
    public const TRIGGER_TYPE = 'turno_cancelled_advance';

    private TurnoAdvanceOfferCandidateFinder $finder;

    public function __construct(?TurnoAdvanceOfferCandidateFinder $finder = null)
    {
        $this->finder = $finder ?? new TurnoAdvanceOfferCandidateFinder();
    }

    public function onTurnoCancelled(Turno $cancelled): void
    {
        if (!(Yii::$app->params['autonomous_agent_advance_offer_enabled'] ?? true)) {
            return;
        }

        $config = AutonomousAgentMetadata::loadAgent(self::AGENT_ID) ?? [];
        $slot = $this->slotFromTurno($cancelled);
        if ($slot === null) {
            return;
        }
        if (!$this->isFarEnough($slot, $config, 'min_lead_minutes_to_offer', 1440)) {
            Yii::info('AdvanceOffer: slot demasiado próximo turno=' . (int) $cancelled->id_turnos, 'turno-advance');
            return;
        }
        if (!TurnoSlotOccupancyService::estaDisponibleSlot(
            (int) $slot['id_profesional_efector_servicio'],
            (string) $slot['fecha'],
            (string) $slot['hora'],
            (int) $cancelled->id_turnos
        )) {
            return;
        }

        $existing = TurnoAdvanceCampaign::findOne(['id_cancelled_turno' => (int) $cancelled->id_turnos]);
        if ($existing !== null) {
            return;
        }

        $now = date('Y-m-d H:i:s');
        $campaign = new TurnoAdvanceCampaign();
        $campaign->id_cancelled_turno = (int) $cancelled->id_turnos;
        $campaign->id_efector = (int) $slot['id_efector'];
        $campaign->id_servicio = (int) $slot['id_servicio'];
        $campaign->id_profesional_efector_servicio = (int) $slot['id_profesional_efector_servicio'];
        $campaign->fecha = (string) $slot['fecha'];
        $campaign->hora = (string) $slot['hora'];
        $campaign->modalidad = (string) $slot['modalidad'];
        $campaign->estado = TurnoAdvanceCampaign::ESTADO_ACTIVE;
        $campaign->current_sequence = 0;
        $campaign->next_run_at = $now;
        $campaign->created_at = $now;
        $campaign->save(false);

        $this->processCampaign($campaign, $config);
    }

    /**
     * Cierra campañas activas cuyo slot fue ocupado por una reserva normal.
     */
    public function notifySlotTakenByReservation(Turno $turno): void
    {
        $idPes = (int) ($turno->id_profesional_efector_servicio ?? 0);
        $fecha = trim((string) ($turno->fecha ?? ''));
        $hora = $this->normalizeHora((string) ($turno->hora ?? ''));
        if ($idPes <= 0 || $fecha === '' || $hora === '') {
            return;
        }
        $campaigns = TurnoAdvanceCampaign::find()
            ->where([
                'estado' => TurnoAdvanceCampaign::ESTADO_ACTIVE,
                'id_profesional_efector_servicio' => $idPes,
                'fecha' => $fecha,
                'hora' => $hora,
            ])
            ->all();
        foreach ($campaigns as $campaign) {
            $campaign->id_turno_filled = (int) $turno->id_turnos;
            $this->stopCampaign($campaign, 'reserved_by_other', 'filled');
        }
    }

    /**
     * Procesa campañas vencidas / próximas (cron).
     *
     * @return array{processed: int, offered: int, closed: int}
     */
    public function processDue(?int $limit = 50): array
    {
        if (!(Yii::$app->params['autonomous_agent_advance_offer_enabled'] ?? true)) {
            return ['processed' => 0, 'offered' => 0, 'closed' => 0];
        }
        $limit = max(1, min(200, (int) ($limit ?? 50)));
        $config = AutonomousAgentMetadata::loadAgent(self::AGENT_ID) ?? [];
        $now = date('Y-m-d H:i:s');
        $ids = TurnoAdvanceCampaign::find()
            ->select(['id'])
            ->where(['estado' => TurnoAdvanceCampaign::ESTADO_ACTIVE])
            ->andWhere(['<=', 'next_run_at', $now])
            ->orderBy(['next_run_at' => SORT_ASC, 'id' => SORT_ASC])
            ->limit($limit)
            ->column();

        $processed = 0;
        $offered = 0;
        $closed = 0;
        foreach ($ids as $id) {
            $campaign = TurnoAdvanceCampaign::findOne((int) $id);
            if ($campaign === null) {
                continue;
            }
            $processed++;
            $result = $this->processCampaign($campaign, $config);
            if ($result === 'offered') {
                $offered++;
            } elseif (in_array($result, ['exhausted', 'stopped', 'filled'], true)) {
                $closed++;
            }
        }

        return compact('processed', 'offered', 'closed');
    }

    /**
     * Recupera cancelaciones nativas recientes sin campaña.
     *
     * @return array{scanned: int, started: int}
     */
    public function repairMissingCampaigns(?int $limit = 50): array
    {
        if (!(Yii::$app->params['autonomous_agent_advance_offer_enabled'] ?? true)) {
            return ['scanned' => 0, 'started' => 0];
        }
        $limit = max(1, min(200, (int) ($limit ?? 50)));
        $since = date('Y-m-d H:i:s', time() - 7 * 86400);
        $rows = Turno::find()
            ->alias('t')
            ->where(['t.estado' => Turno::ESTADO_CANCELADO])
            ->andWhere([
                'or',
                ['>=', 't.deleted_at', $since],
                [
                    'and',
                    ['t.deleted_at' => null],
                    ['>=', 't.fecha_mod', $since],
                ],
            ])
            ->andWhere([
                'not exists',
                (new \yii\db\Query())
                    ->from(['c' => TurnoAdvanceCampaign::tableName()])
                    ->where('c.id_cancelled_turno = t.id_turnos'),
            ])
            ->orderBy([
                new Expression('COALESCE(t.deleted_at, t.fecha_mod) DESC'),
            ])
            ->limit($limit)
            ->all();

        $started = 0;
        foreach ($rows as $turno) {
            $before = TurnoAdvanceCampaign::find()->where(['id_cancelled_turno' => (int) $turno->id_turnos])->exists();
            $this->onTurnoCancelled($turno);
            if (!$before && TurnoAdvanceCampaign::find()->where(['id_cancelled_turno' => (int) $turno->id_turnos])->exists()) {
                $started++;
            }
        }

        return ['scanned' => count($rows), 'started' => $started];
    }

    /**
     * @param array<string, mixed> $config
     * @return string offered|skipped|exhausted|stopped|filled|noop
     */
    public function processCampaign(TurnoAdvanceCampaign $campaign, ?array $config = null): string
    {
        $config = $config ?? AutonomousAgentMetadata::loadAgent(self::AGENT_ID) ?? [];
        $db = TurnoAdvanceCampaign::getDb();
        $ownsTx = $db->getTransaction() === null;
        $tx = $ownsTx ? $db->beginTransaction() : null;
        try {
            $locked = $db->createCommand(
                'SELECT id FROM {{%turno_advance_campaign}} WHERE id = :id AND estado = :estado FOR UPDATE'
            )->bindValues([
                ':id' => (int) $campaign->id,
                ':estado' => TurnoAdvanceCampaign::ESTADO_ACTIVE,
            ])->queryScalar();
            if (!$locked) {
                if ($tx !== null) {
                    $tx->commit();
                }
                return 'noop';
            }
            $campaign->refresh();
            if ($campaign->estado !== TurnoAdvanceCampaign::ESTADO_ACTIVE) {
                if ($tx !== null) {
                    $tx->commit();
                }
                return 'noop';
            }

            $result = $this->processCampaignLocked($campaign, $config);
            if ($tx !== null) {
                $tx->commit();
            }

            return $result;
        } catch (\Throwable $e) {
            if ($tx !== null && $tx->isActive) {
                $tx->rollBack();
            }
            throw $e;
        }
    }

    /**
     * @param array<string, mixed> $config
     * @return string offered|skipped|exhausted|stopped|filled|noop
     */
    private function processCampaignLocked(TurnoAdvanceCampaign $campaign, array $config): string
    {
        $this->expirePendingOffers($campaign);

        if (!$this->slotStillAvailable($campaign)) {
            return $this->stopCampaign($campaign, 'slot_taken', 'filled');
        }
        if (!$this->isFarEnough([
            'fecha' => $campaign->fecha,
            'hora' => $campaign->hora,
        ], $config, 'stop_new_offers_minutes_before_slot', 360)) {
            return $this->stopCampaign($campaign, 'too_close', 'stopped');
        }

        // Cerrar oferta pendiente actual si venció el step.
        $pending = TurnoAdvanceOffer::find()
            ->where(['id_campaign' => (int) $campaign->id, 'estado' => TurnoAdvanceOffer::ESTADO_PENDING])
            ->orderBy(['sequence' => SORT_DESC])
            ->one();
        if ($pending !== null) {
            $expires = strtotime((string) $pending->expires_at);
            if ($expires !== false && $expires > time()) {
                // Aún en ventana; reprogramar next_run_at al vencimiento.
                $campaign->next_run_at = $pending->expires_at;
                $campaign->updated_at = date('Y-m-d H:i:s');
                $campaign->save(false, ['next_run_at', 'updated_at']);

                return 'noop';
            }
            $pending->estado = TurnoAdvanceOffer::ESTADO_EXPIRED;
            $pending->decided_at = date('Y-m-d H:i:s');
            $pending->result_detail = 'step_elapsed';
            $pending->save(false);
            $this->recordCanonical(
                (int) $campaign->id_cancelled_turno,
                (int) $pending->subject_persona_id,
                TurnoEventoAudit::EVENT_APPOINTMENT_ADVANCE_EXPIRED,
                'advance-expired:' . (int) $pending->id,
                ['offer_id' => (int) $pending->id, 'campaign_id' => (int) $campaign->id]
            );
        }

        for ($attempt = 0; $attempt < 20; $attempt++) {
            $candidate = $this->finder->nextCandidate($campaign, $config);
            if ($candidate === null) {
                return $this->stopCampaign($campaign, 'no_candidates', 'exhausted');
            }
            if ($this->offerToCandidate($campaign, $candidate, $config)) {
                return 'offered';
            }
            $campaign->refresh();
            if ($campaign->estado !== TurnoAdvanceCampaign::ESTADO_ACTIVE) {
                return 'noop';
            }
        }

        return 'skipped';
    }

    /**
     * @param array<string, mixed> $config
     */
    private function offerToCandidate(TurnoAdvanceCampaign $campaign, Turno $candidate, array $config): bool
    {
        $step = (int) ($config['offer_step_minutes'] ?? 120);
        if ($step < 1) {
            $step = 120;
        }
        $now = date('Y-m-d H:i:s');
        $expiresAt = date('Y-m-d H:i:s', time() + $step * 60);
        $token = bin2hex(random_bytes(16));
        $seq = (int) $campaign->current_sequence + 1;

        $offer = new TurnoAdvanceOffer();
        $offer->id_campaign = (int) $campaign->id;
        $offer->sequence = $seq;
        $offer->id_turno_candidate = (int) $candidate->id_turnos;
        $offer->subject_persona_id = (int) $candidate->id_persona;
        $offer->offer_token = $token;
        $offer->estado = TurnoAdvanceOffer::ESTADO_PENDING;
        $offer->offered_at = $now;
        $offer->expires_at = $expiresAt;
        $offer->created_at = $now;
        try {
            $offer->save(false);
        } catch (\Throwable $e) {
            Yii::warning('AdvanceOffer duplicate sequence: ' . $e->getMessage(), 'turno-advance');
            return false;
        }

        $fechaFmt = (string) $campaign->fecha;
        $horaFmt = substr($this->normalizeHora($campaign->hora), 0, 5);
        $texts = is_array($config['texts'] ?? null) ? $config['texts'] : [];
        $title = (string) ($texts['title'] ?? 'Se liberó un turno más temprano');
        $bodyTpl = (string) ($texts['body'] ?? 'Hay un horario el {fecha} a las {hora}. Podés adelantar tu consulta si todavía está disponible.');
        $body = str_replace(['{fecha}', '{hora}'], [$fechaFmt, $horaFmt], $bodyTpl);
        $actionLabel = (string) ($texts['action_label'] ?? 'Adelantar mi turno');
        $pushType = (string) ($config['push_type'] ?? PushNotificationTypes::TURNO_ADVANCE_OFFER);

        $notif = (new PushNotificationSender())->sendToPersona(
            (int) $candidate->id_persona,
            [
                'type' => $pushType,
                'action' => (string) ($config['action'] ?? 'adelantar_turno'),
                'action_label' => $actionLabel,
                'offer_token' => $token,
                'campaign_id' => (string) $campaign->id,
                'id_turno' => (string) $candidate->id_turnos,
                'fecha' => $fechaFmt,
                'hora' => $horaFmt,
            ],
            $title,
            $body,
            true,
            [
                'idempotency_key' => 'turno-advance:' . (int) $offer->id,
                'context_handler_id' => TurnoAdvancePushReceiptProjector::HANDLER_ID,
                'context' => [
                    'id_turno' => (int) $candidate->id_turnos,
                    'offer_id' => (int) $offer->id,
                    'campaign_id' => (int) $campaign->id,
                    'id_cancelled_turno' => (int) $campaign->id_cancelled_turno,
                ],
            ]
        );
        if ($notif === null) {
            $offer->estado = TurnoAdvanceOffer::ESTADO_EXPIRED;
            $offer->decided_at = $now;
            $offer->result_detail = 'push_failed';
            $offer->save(false);
            $campaign->current_sequence = $seq;
            $campaign->next_run_at = $now;
            $campaign->updated_at = $now;
            $campaign->save(false);
            $this->recordCanonical(
                (int) $campaign->id_cancelled_turno,
                (int) $candidate->id_persona,
                TurnoEventoAudit::EVENT_APPOINTMENT_ADVANCE_EXPIRED,
                'advance-push-failed:' . (int) $offer->id,
                ['offer_id' => (int) $offer->id, 'campaign_id' => (int) $campaign->id]
            );

            return false;
        }
        if (!empty($notif->public_ref)) {
            $offer->notification_ref = (string) $notif->public_ref;
            $offer->save(false, ['notification_ref']);
        }

        $campaign->current_sequence = $seq;
        $campaign->next_run_at = $expiresAt;
        $campaign->updated_at = $now;
        $campaign->save(false);

        $this->recordCanonical(
            (int) $campaign->id_cancelled_turno,
            (int) $candidate->id_persona,
            TurnoEventoAudit::EVENT_APPOINTMENT_ADVANCE_OFFERED,
            'advance-offered:' . (int) $offer->id,
            [
                'offer_id' => (int) $offer->id,
                'campaign_id' => (int) $campaign->id,
                'id_turno_candidate' => (int) $candidate->id_turnos,
                'expires_at' => $expiresAt,
            ]
        );

        AgentRunRecorder::record(
            self::AGENT_ID,
            self::TRIGGER_TYPE,
            'offer_sent',
            (int) $campaign->id,
            null,
            (int) $candidate->id_persona,
            'nearest_first',
            [
                'campaign_id' => (int) $campaign->id,
                'offer_id' => (int) $offer->id,
                'id_turno_candidate' => (int) $candidate->id_turnos,
                'fecha' => $fechaFmt,
                'hora' => $horaFmt,
                'sequence' => $seq,
            ],
            ['offer_token' => $token]
        );

        return true;
    }

    private function expirePendingOffers(TurnoAdvanceCampaign $campaign): void
    {
        $now = date('Y-m-d H:i:s');
        $pendings = TurnoAdvanceOffer::find()
            ->where(['id_campaign' => (int) $campaign->id, 'estado' => TurnoAdvanceOffer::ESTADO_PENDING])
            ->andWhere(['<', 'expires_at', $now])
            ->all();
        foreach ($pendings as $pending) {
            $pending->estado = TurnoAdvanceOffer::ESTADO_EXPIRED;
            $pending->decided_at = $now;
            $pending->result_detail = 'ttl_elapsed';
            $pending->save(false);
            $this->recordCanonical(
                (int) $campaign->id_cancelled_turno,
                (int) $pending->subject_persona_id,
                TurnoEventoAudit::EVENT_APPOINTMENT_ADVANCE_EXPIRED,
                'advance-expired:' . (int) $pending->id,
                ['offer_id' => (int) $pending->id, 'campaign_id' => (int) $campaign->id]
            );
        }
    }

    private function slotStillAvailable(TurnoAdvanceCampaign $campaign): bool
    {
        return TurnoSlotOccupancyService::estaDisponibleSlot(
            (int) $campaign->id_profesional_efector_servicio,
            (string) $campaign->fecha,
            (string) $campaign->hora,
            (int) $campaign->id_cancelled_turno
        );
    }

    private function stopCampaign(TurnoAdvanceCampaign $campaign, string $reason, string $outcome): string
    {
        $estado = $outcome === 'filled'
            ? TurnoAdvanceCampaign::ESTADO_FILLED
            : ($outcome === 'exhausted'
                ? TurnoAdvanceCampaign::ESTADO_EXHAUSTED
                : TurnoAdvanceCampaign::ESTADO_STOPPED);
        $campaign->estado = $estado;
        $campaign->stop_reason = $reason;
        $campaign->next_run_at = null;
        $campaign->updated_at = date('Y-m-d H:i:s');
        $campaign->save(false);

        $pending = TurnoAdvanceOffer::find()
            ->where(['id_campaign' => (int) $campaign->id, 'estado' => TurnoAdvanceOffer::ESTADO_PENDING])
            ->all();
        foreach ($pending as $p) {
            $p->estado = TurnoAdvanceOffer::ESTADO_UNAVAILABLE;
            $p->decided_at = date('Y-m-d H:i:s');
            $p->result_detail = $reason;
            $p->save(false);
            $this->recordCanonical(
                (int) $campaign->id_cancelled_turno,
                (int) $p->subject_persona_id,
                TurnoEventoAudit::EVENT_APPOINTMENT_ADVANCE_UNAVAILABLE,
                'advance-unavailable:' . (int) $p->id,
                ['offer_id' => (int) $p->id, 'reason' => $reason]
            );
        }

        AgentRunRecorder::record(
            self::AGENT_ID,
            self::TRIGGER_TYPE,
            $outcome,
            (int) $campaign->id,
            null,
            null,
            null,
            ['campaign_id' => (int) $campaign->id, 'reason' => $reason]
        );

        return $outcome;
    }

    /**
     * @param array<string, mixed> $slot
     * @param array<string, mixed> $config
     */
    private function isFarEnough(array $slot, array $config, string $key, int $default): bool
    {
        $min = (int) ($config[$key] ?? $default);
        if ($min < 0) {
            $min = $default;
        }
        $dt = strtotime((string) $slot['fecha'] . ' ' . $this->normalizeHora((string) $slot['hora']) . ':00');

        return $dt !== false && $dt > time() + $min * 60;
    }

    /**
     * @return array{id_efector: int, id_servicio: int, id_profesional_efector_servicio: int, fecha: string, hora: string, modalidad: string}|null
     */
    private function slotFromTurno(Turno $turno): ?array
    {
        $idPes = (int) ($turno->id_profesional_efector_servicio ?? 0);
        $idEfector = (int) ($turno->id_efector ?? 0);
        $idServicio = (int) ($turno->id_servicio_asignado ?? 0);
        $fecha = trim((string) ($turno->fecha ?? ''));
        $hora = $this->normalizeHora((string) ($turno->hora ?? ''));
        if ($idPes <= 0 || $idEfector <= 0 || $idServicio <= 0 || $fecha === '' || $hora === '') {
            return null;
        }

        return [
            'id_efector' => $idEfector,
            'id_servicio' => $idServicio,
            'id_profesional_efector_servicio' => $idPes,
            'fecha' => $fecha,
            'hora' => $hora,
            'modalidad' => (string) ($turno->tipo_atencion ?: Turno::TIPO_ATENCION_PRESENCIAL),
        ];
    }

    private function normalizeHora(string $hora): string
    {
        $n = TurnoResolucion::normalizarHora($hora);

        return $n !== '' ? substr($n, 0, 5) : substr(trim($hora), 0, 5);
    }

    /**
     * @param array<string, mixed> $meta
     */
    private function recordCanonical(
        int $idTurnoAnchor,
        int $idPersona,
        string $eventCode,
        string $idempotencyKey,
        array $meta
    ): void {
        if ($idTurnoAnchor <= 0 || $idPersona <= 0) {
            return;
        }
        (new TurnoCanonicalEventService())->record(TurnoCanonicalEventCommand::create(
            $idTurnoAnchor,
            $idPersona,
            $eventCode,
            TurnoEventoAudit::ACTOR_SISTEMA,
            $idempotencyKey,
            TurnoEventoAudit::QUALITY_NATIVE,
            null,
            'push',
            'advance_offer',
            null,
            null,
            $meta
        ));
    }
}
