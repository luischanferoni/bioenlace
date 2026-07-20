<?php

namespace common\components\Domain\Scheduling\Service;

use common\components\Domain\Scheduling\Service\BehaviorProfile\TurnoCanonicalEventCommand;
use common\components\Domain\Scheduling\Service\BehaviorProfile\TurnoCanonicalEventService;
use common\components\Platform\Agent\AgentRunRecorder;
use common\components\Platform\Core\Product\AutonomousAgentMetadata;
use common\models\Scheduling\Turno;
use common\models\Scheduling\TurnoAdvanceCampaign;
use common\models\Scheduling\TurnoAdvanceOffer;
use common\models\TurnoEventoAudit;
use common\models\TurnoResolucion;
use Yii;

/**
 * Aceptación de oferta de adelantamiento: reprograma el turno existente sin hold previo.
 */
final class TurnoAdvanceOfferAcceptService
{
    /**
     * @return array{id_turno: int, fecha: string, hora: string, campaign_id: int}
     */
    public function accept(string $token, int $idPersona, string $actorType = TurnoEventoAudit::ACTOR_PACIENTE): array
    {
        $token = trim($token);
        if ($token === '' || $idPersona <= 0) {
            throw new \InvalidArgumentException('Oferta inválida.');
        }

        $db = Turno::getDb();
        $tx = $db->beginTransaction();
        try {
            /** @var TurnoAdvanceOffer|null $offer */
            $offer = TurnoAdvanceOffer::find()
                ->where(['offer_token' => $token, 'subject_persona_id' => $idPersona])
                ->one();
            if ($offer === null) {
                throw new \InvalidArgumentException('Oferta no encontrada.');
            }

            /** @var TurnoAdvanceCampaign|null $campaign */
            $campaign = TurnoAdvanceCampaign::find()
                ->where(['id' => (int) $offer->id_campaign])
                ->one();
            if ($campaign === null) {
                throw new \InvalidArgumentException('Campaña no encontrada.');
            }

            // Lock fila de campaña.
            $locked = $db->createCommand(
                'SELECT id FROM {{%turno_advance_campaign}} WHERE id = :id FOR UPDATE'
            )->bindValue(':id', (int) $campaign->id)->queryScalar();
            if (!$locked) {
                throw new \InvalidArgumentException('Campaña no encontrada.');
            }
            $campaign->refresh();
            $offer->refresh();

            if ($offer->estado === TurnoAdvanceOffer::ESTADO_ACCEPTED
                && $campaign->estado === TurnoAdvanceCampaign::ESTADO_FILLED
                && (int) $campaign->id_turno_filled === (int) $offer->id_turno_candidate
            ) {
                $turnoDone = Turno::findActive()->andWhere(['id_turnos' => (int) $offer->id_turno_candidate])->one();
                $tx->commit();
                return [
                    'id_turno' => (int) $offer->id_turno_candidate,
                    'fecha' => (string) ($turnoDone->fecha ?? $campaign->fecha),
                    'hora' => substr(TurnoResolucion::normalizarHora((string) ($turnoDone->hora ?? $campaign->hora)), 0, 5),
                    'campaign_id' => (int) $campaign->id,
                ];
            }

            if ($offer->estado !== TurnoAdvanceOffer::ESTADO_PENDING) {
                throw new \InvalidArgumentException('La oferta ya no está disponible.');
            }
            if ($campaign->estado !== TurnoAdvanceCampaign::ESTADO_ACTIVE) {
                throw new \InvalidArgumentException('El horario ya no está disponible.');
            }
            $expires = strtotime((string) $offer->expires_at);
            if ($expires === false || $expires < time()) {
                $offer->estado = TurnoAdvanceOffer::ESTADO_EXPIRED;
                $offer->decided_at = date('Y-m-d H:i:s');
                $offer->result_detail = 'expired_on_accept';
                $offer->save(false);
                $tx->commit();
                throw new \InvalidArgumentException('La oferta venció.');
            }

            /** @var Turno|null $turno */
            $turno = Turno::findActive()
                ->andWhere([
                    'id_turnos' => (int) $offer->id_turno_candidate,
                    'id_persona' => $idPersona,
                    'estado' => Turno::ESTADO_PENDIENTE,
                ])
                ->one();
            if ($turno === null) {
                throw new \InvalidArgumentException('Tu turno ya no admite adelantamiento.');
            }

            $horaDest = TurnoResolucion::normalizarHora((string) $campaign->hora);
            if (!TurnoSlotOccupancyService::estaDisponibleSlot(
                (int) $campaign->id_profesional_efector_servicio,
                (string) $campaign->fecha,
                $horaDest,
                (int) $turno->id_turnos
            )) {
                $this->markUnavailable($offer, $campaign, 'slot_taken');
                $tx->commit();
                throw new \InvalidArgumentException('El horario ya no está disponible.');
            }

            if (!TurnoSlotClaimService::moveClaim(
                (int) $turno->id_turnos,
                (int) $campaign->id_profesional_efector_servicio,
                (string) $campaign->fecha,
                $horaDest
            )) {
                $this->markUnavailable($offer, $campaign, 'claim_lost');
                $tx->commit();
                throw new \InvalidArgumentException('El horario ya no está disponible.');
            }

            $before = TurnoLifecycleService::scheduleSnapshot($turno);
            $turno->fecha = (string) $campaign->fecha;
            $turno->hora = $horaDest . ':00';
            $turno->id_profesional_efector_servicio = (int) $campaign->id_profesional_efector_servicio;
            $turno->id_efector = (int) $campaign->id_efector;
            $turno->id_servicio_asignado = (int) $campaign->id_servicio;
            $turno->tipo_atencion = (string) $campaign->modalidad;
            TurnoReservaSlotService::aplicarCamposReserva($turno, (int) $turno->id_turnos);

            (new TurnoLifecycleService())->reprogramar(
                $turno,
                $before,
                $actorType,
                'app',
                null,
                false,
                false
            );

            $now = date('Y-m-d H:i:s');
            $offer->estado = TurnoAdvanceOffer::ESTADO_ACCEPTED;
            $offer->decided_at = $now;
            $offer->result_detail = 'accepted';
            $offer->save(false);

            $campaign->estado = TurnoAdvanceCampaign::ESTADO_FILLED;
            $campaign->id_turno_filled = (int) $turno->id_turnos;
            $campaign->stop_reason = 'accepted';
            $campaign->next_run_at = null;
            $campaign->updated_at = $now;
            $campaign->save(false);

            // Otras ofertas pendientes → unavailable
            TurnoAdvanceOffer::updateAll(
                [
                    'estado' => TurnoAdvanceOffer::ESTADO_UNAVAILABLE,
                    'decided_at' => $now,
                    'result_detail' => 'filled_by_other',
                ],
                [
                    'and',
                    ['id_campaign' => (int) $campaign->id],
                    ['estado' => TurnoAdvanceOffer::ESTADO_PENDING],
                    ['<>', 'id', (int) $offer->id],
                ]
            );

            (new TurnoCanonicalEventService())->record(TurnoCanonicalEventCommand::create(
                (int) $turno->id_turnos,
                $idPersona,
                TurnoEventoAudit::EVENT_APPOINTMENT_ADVANCE_ACCEPTED,
                $actorType,
                'advance-accepted:' . (int) $offer->id,
                TurnoEventoAudit::QUALITY_NATIVE,
                Yii::$app->user->id ? (int) Yii::$app->user->id : null,
                'app',
                'advance_offer',
                null,
                null,
                [
                    'offer_id' => (int) $offer->id,
                    'campaign_id' => (int) $campaign->id,
                    'id_cancelled_turno' => (int) $campaign->id_cancelled_turno,
                    'before' => $before,
                    'after' => TurnoLifecycleService::scheduleSnapshot($turno),
                ]
            ));

            AgentRunRecorder::record(
                TurnoAdvanceOfferAgent::AGENT_ID,
                TurnoAdvanceOfferAgent::TRIGGER_TYPE,
                'accepted',
                (int) $campaign->id,
                null,
                $idPersona,
                (string) ((AutonomousAgentMetadata::loadAgent(TurnoAdvanceOfferAgent::AGENT_ID) ?? [])['order']
                    ?? 'd2_then_d1_same_halfday'),
                [
                    'offer_id' => (int) $offer->id,
                    'id_turno' => (int) $turno->id_turnos,
                ]
            );

            $tx->commit();
        } catch (\Throwable $e) {
            if ($tx->isActive) {
                $tx->rollBack();
            }
            throw $e;
        }

        \common\components\Domain\Integrations\Scheduling\Service\TurnoFhirOutboundNotifier::afterEstadoChanged($turno);

        return [
            'id_turno' => (int) $turno->id_turnos,
            'fecha' => (string) $turno->fecha,
            'hora' => substr(TurnoResolucion::normalizarHora((string) $turno->hora), 0, 5),
            'campaign_id' => (int) $campaign->id,
        ];
    }

    private function markUnavailable(
        TurnoAdvanceOffer $offer,
        TurnoAdvanceCampaign $campaign,
        string $reason
    ): void {
        $now = date('Y-m-d H:i:s');
        $offer->estado = TurnoAdvanceOffer::ESTADO_UNAVAILABLE;
        $offer->decided_at = $now;
        $offer->result_detail = $reason;
        $offer->save(false);

        if ($campaign->estado === TurnoAdvanceCampaign::ESTADO_ACTIVE) {
            $campaign->estado = TurnoAdvanceCampaign::ESTADO_FILLED;
            $campaign->stop_reason = $reason;
            $campaign->next_run_at = null;
            $campaign->updated_at = $now;
            $campaign->save(false);
        }

        (new TurnoCanonicalEventService())->record(TurnoCanonicalEventCommand::create(
            (int) $campaign->id_cancelled_turno,
            (int) $offer->subject_persona_id,
            TurnoEventoAudit::EVENT_APPOINTMENT_ADVANCE_UNAVAILABLE,
            TurnoEventoAudit::ACTOR_SISTEMA,
            'advance-unavailable:' . (int) $offer->id . ':' . $reason,
            TurnoEventoAudit::QUALITY_NATIVE,
            null,
            'app',
            'advance_offer',
            null,
            null,
            ['offer_id' => (int) $offer->id, 'reason' => $reason]
        ));
    }
}
