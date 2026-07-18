<?php

namespace common\components\Domain\Scheduling\Service;

use common\components\Platform\Agent\AgentRunRecorder;
use common\components\Platform\Core\Product\AutonomousAgentMetadata;
use common\components\Platform\Core\Service\Push\PushNotificationSender;
use common\components\Platform\Core\Service\Push\PushNotificationTypes;
use common\components\Domain\Scheduling\Service\BehaviorProfile\TurnoCanonicalEventCommand;
use common\components\Domain\Scheduling\Service\BehaviorProfile\TurnoCanonicalEventService;
use common\models\Scheduling\Turno;
use common\models\Scheduling\TurnoWaitlistEntry;
use common\models\Scheduling\TurnoWaitlistSlotOffer;
use common\models\TurnoEventoAudit;
use Yii;

/**
 * Agente A03 v1: ofrece hueco liberado al primer inscripto FIFO en lista de espera.
 */
final class TurnoWaitlistFillAgent
{
    public const AGENT_ID = 'turno-waitlist-fill';

    public const TRIGGER_TYPE = 'turno_cancelled_slot';

    private TurnoWaitlistService $waitlist;

    public function __construct(?TurnoWaitlistService $waitlist = null)
    {
        $this->waitlist = $waitlist ?? new TurnoWaitlistService();
    }

    public function onTurnoCancelled(Turno $cancelled): void
    {
        if (!(Yii::$app->params['autonomous_agent_waitlist_enabled'] ?? true)) {
            return;
        }

        $config = AutonomousAgentMetadata::loadAgent(self::AGENT_ID) ?? [];
        $slot = TurnoWaitlistService::slotPayloadFromTurno($cancelled);
        if ($slot === null) {
            return;
        }

        if ((int) ($slot['id_efector'] ?? 0) <= 0 || (int) ($slot['id_servicio'] ?? 0) <= 0) {
            return;
        }

        if (!$this->isSlotFarEnoughInFuture($slot, $config)) {
            Yii::info('Waitlist: slot demasiado próximo turno=' . (int) $cancelled->id_turnos, 'turno-waitlist');

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

        $offer = $this->createSlotOffer($cancelled, $slot);
        $this->offerToNextCandidate($offer, $config);
    }

    public function offerToNextCandidate(TurnoWaitlistSlotOffer $offer, ?array $config = null): bool
    {
        if ($offer->estado !== TurnoWaitlistSlotOffer::ESTADO_PENDING) {
            return false;
        }

        $config = $config ?? AutonomousAgentMetadata::loadAgent(self::AGENT_ID) ?? [];
        $candidate = $this->waitlist->findNextFifoCandidate(
            (int) $offer->id_efector,
            (int) $offer->id_servicio,
            (int) $offer->id_profesional_efector_servicio,
            (int) $offer->id,
            $config
        );

        if ($candidate === null) {
            $offer->estado = TurnoWaitlistSlotOffer::ESTADO_EXHAUSTED;
            $offer->updated_at = date('Y-m-d H:i:s');
            $offer->save(false);
            AgentRunRecorder::record(
                self::AGENT_ID,
                self::TRIGGER_TYPE,
                'exhausted',
                (int) $offer->id,
                null,
                null,
                null,
                ['slot_offer_id' => (int) $offer->id]
            );

            return false;
        }

        $ttl = (int) ($config['offer_ttl_minutes'] ?? Yii::$app->params['turnosWaitlist']['offer_ttl_minutes'] ?? 15);
        if ($ttl < 1) {
            $ttl = 15;
        }
        $token = bin2hex(random_bytes(16));
        $expiresAt = date('Y-m-d H:i:s', time() + $ttl * 60);

        $candidate->estado = TurnoWaitlistEntry::ESTADO_OFFERED;
        $candidate->slot_offer_id = (int) $offer->id;
        $candidate->offer_token = $token;
        $candidate->offer_expires_at = $expiresAt;
        $candidate->updated_at = date('Y-m-d H:i:s');
        $candidate->save(false);

        $slot = $offer->decodeSlot();
        $fechaFmt = (string) ($slot['fecha'] ?? $offer->fecha);
        $horaFmt = substr((string) ($slot['hora'] ?? $offer->hora), 0, 5);

        (new PushNotificationSender())->sendToPersona(
            (int) $candidate->subject_persona_id,
            [
                'type' => PushNotificationTypes::TURNO_WAITLIST_OFFER,
                'offer_token' => $token,
                'slot_offer_id' => (string) $offer->id,
                'fecha' => $fechaFmt,
                'hora' => $horaFmt,
            ],
            'Hay un turno disponible',
            'Se liberó un horario el ' . $fechaFmt . ' a las ' . $horaFmt . '. Tenés ' . $ttl . ' min para confirmarlo.',
            true
        );

        $anchorTurnoId = (int) ($offer->id_cancelled_turno ?: 0);
        if ($anchorTurnoId > 0 && (int) $candidate->subject_persona_id > 0) {
            (new TurnoCanonicalEventService())->record(TurnoCanonicalEventCommand::create(
                $anchorTurnoId,
                (int) $candidate->subject_persona_id,
                TurnoEventoAudit::EVENT_WAITLIST_OFFERED,
                TurnoEventoAudit::ACTOR_SISTEMA,
                'waitlist-offered:' . (int) $candidate->id . ':' . (int) $offer->id,
                TurnoEventoAudit::QUALITY_NATIVE,
                null,
                'push',
                'waitlist_fill',
                null,
                null,
                [
                    'entry_id' => (int) $candidate->id,
                    'slot_offer_id' => (int) $offer->id,
                    'offer_expires_at' => $expiresAt,
                ]
            ));
        }

        AgentRunRecorder::record(
            self::AGENT_ID,
            self::TRIGGER_TYPE,
            'offer_sent',
            (int) $offer->id,
            null,
            (int) $candidate->subject_persona_id,
            'fifo',
            [
                'entry_id' => (int) $candidate->id,
                'fecha' => $fechaFmt,
                'hora' => $horaFmt,
            ],
            ['slot_offer_id' => (int) $offer->id, 'offer_token' => $token]
        );

        return true;
    }

    /**
     * @param array<string, mixed> $slot
     * @param array<string, mixed> $config
     */
    private function isSlotFarEnoughInFuture(array $slot, array $config): bool
    {
        $min = (int) ($config['min_minutes_before_slot'] ?? Yii::$app->params['turnosWaitlist']['min_minutes_before_slot'] ?? 15);
        if ($min < 0) {
            $min = 15;
        }
        $dt = strtotime((string) $slot['fecha'] . ' ' . (string) $slot['hora'] . ':00');

        return $dt !== false && $dt > time() + $min * 60;
    }

    /**
     * @param array<string, mixed> $slot
     */
    private function createSlotOffer(Turno $cancelled, array $slot): TurnoWaitlistSlotOffer
    {
        $now = date('Y-m-d H:i:s');
        $offer = new TurnoWaitlistSlotOffer();
        $offer->id_cancelled_turno = (int) $cancelled->id_turnos;
        $offer->id_efector = (int) $slot['id_efector'];
        $offer->id_servicio = (int) $slot['id_servicio'];
        $offer->id_profesional_efector_servicio = (int) $slot['id_profesional_efector_servicio'];
        $offer->fecha = (string) $slot['fecha'];
        $offer->hora = (string) $slot['hora'] . ':00';
        $offer->slot_json = json_encode($slot, JSON_UNESCAPED_UNICODE);
        $offer->estado = TurnoWaitlistSlotOffer::ESTADO_PENDING;
        $offer->created_at = $now;
        $offer->save(false);

        return $offer;
    }
}
