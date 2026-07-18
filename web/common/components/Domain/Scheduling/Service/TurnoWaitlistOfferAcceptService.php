<?php

namespace common\components\Domain\Scheduling\Service;

use common\components\Platform\Agent\AgentRunRecorder;
use common\components\Domain\Scheduling\Service\BehaviorProfile\TurnoCanonicalEventCommand;
use common\components\Domain\Scheduling\Service\BehaviorProfile\TurnoCanonicalEventService;
use common\models\Scheduling\Turno;
use common\models\Scheduling\TurnoWaitlistEntry;
use common\models\Scheduling\TurnoWaitlistSlotOffer;
use common\models\TurnoEventoAudit;
use Yii;

/**
 * Acepta oferta de lista de espera y reserva el turno.
 */
final class TurnoWaitlistOfferAcceptService
{
    /**
     * @return array<string, mixed>
     */
    public function accept(string $offerToken, int $subjectPersonaId, ?string $actorType = null): array
    {
        $token = trim($offerToken);
        if ($token === '') {
            throw new \InvalidArgumentException('offer_token requerido.');
        }

        $entry = TurnoWaitlistEntry::findOne([
            'offer_token' => $token,
            'subject_persona_id' => $subjectPersonaId,
            'estado' => TurnoWaitlistEntry::ESTADO_OFFERED,
        ]);
        if ($entry === null) {
            throw new \InvalidArgumentException('Oferta no encontrada o ya utilizada.');
        }

        if ($entry->offer_expires_at !== null && strtotime((string) $entry->offer_expires_at) < time()) {
            $this->expireEntry($entry);
            throw new \InvalidArgumentException('La oferta expiró. Podés quedar en lista para el próximo hueco.');
        }

        $offer = $entry->slotOffer;
        if ($offer === null || $offer->estado !== TurnoWaitlistSlotOffer::ESTADO_PENDING) {
            throw new \InvalidArgumentException('El hueco ya no está disponible.');
        }

        $slot = $offer->decodeSlot();
        $idPes = (int) ($slot['id_profesional_efector_servicio'] ?? $offer->id_profesional_efector_servicio);
        $fecha = (string) ($slot['fecha'] ?? $offer->fecha);
        $hora = substr((string) ($slot['hora'] ?? $offer->hora), 0, 5);

        if (!TurnoSlotOccupancyService::estaDisponibleSlot($idPes, $fecha, $hora, null)) {
            $this->expireEntry($entry);
            (new TurnoWaitlistFillAgent())->offerToNextCandidate($offer);

            throw new \InvalidArgumentException('El horario ya fue tomado. Te ofreceremos el próximo hueco si hay lista.');
        }

        $turno = new Turno();
        $turno->id_persona = $subjectPersonaId;
        $turno->id_profesional_efector_servicio = $idPes;
        $turno->fecha = $fecha;
        $turno->hora = $hora;
        $turno->id_efector = (int) $offer->id_efector;
        $turno->id_servicio_asignado = (int) $offer->id_servicio;
        $turno->estado = Turno::ESTADO_PENDIENTE;

        $actor = $actorType !== null && in_array($actorType, TurnoEventoAudit::actorTypeValues(), true)
            ? $actorType
            : TurnoEventoAudit::ACTOR_PACIENTE;

        $ctx = new TurnoCreacionContext($subjectPersonaId, null, null);
        $result = (new TurnoPersistService())->crear($turno, $ctx);

        $entry->estado = TurnoWaitlistEntry::ESTADO_FULFILLED;
        $entry->id_turno_fulfilled = (int) $result['id'];
        $entry->offer_token = null;
        $entry->updated_at = date('Y-m-d H:i:s');
        $entry->save(false);

        $offer->estado = TurnoWaitlistSlotOffer::ESTADO_FILLED;
        $offer->updated_at = date('Y-m-d H:i:s');
        $offer->save(false);

        (new TurnoCanonicalEventService())->record(TurnoCanonicalEventCommand::create(
            (int) $result['id'],
            $subjectPersonaId,
            TurnoEventoAudit::EVENT_WAITLIST_ACCEPTED,
            $actor,
            'waitlist-accepted:' . (int) $entry->id . ':' . (int) $result['id'],
            TurnoEventoAudit::QUALITY_NATIVE,
            Yii::$app->user->id ?? null,
            'app',
            'waitlist_accept',
            null,
            null,
            [
                'entry_id' => (int) $entry->id,
                'slot_offer_id' => (int) $offer->id,
                'id_cancelled_turno' => (int) ($offer->id_cancelled_turno ?: 0) ?: null,
            ]
        ));

        AgentRunRecorder::record(
            TurnoWaitlistFillAgent::AGENT_ID,
            'waitlist_offer_accepted',
            'fulfilled',
            (int) $offer->id,
            null,
            $subjectPersonaId,
            'fifo',
            ['entry_id' => (int) $entry->id, 'id_turno' => (int) $result['id']]
        );

        return $result;
    }

    private function expireEntry(TurnoWaitlistEntry $entry): void
    {
        $entry->estado = TurnoWaitlistEntry::ESTADO_EXPIRED;
        $entry->offer_token = null;
        $entry->updated_at = date('Y-m-d H:i:s');
        $entry->save(false);
    }
}
