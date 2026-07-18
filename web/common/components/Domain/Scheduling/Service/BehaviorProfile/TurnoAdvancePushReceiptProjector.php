<?php

namespace common\components\Domain\Scheduling\Service\BehaviorProfile;

use common\components\Platform\Core\Service\Notificaciones\PushNotificationReceiptProjectorInterface;
use common\models\PersonaNotificacion;
use common\models\PersonaNotificacionInteraccion;
use common\models\Scheduling\Turno;
use common\models\TurnoEventoAudit;

/**
 * Proyecta DELIVERED/OPENED de ofertas de adelantamiento al stream canónico.
 */
final class TurnoAdvancePushReceiptProjector implements PushNotificationReceiptProjectorInterface
{
    public const HANDLER_ID = 'scheduling.turno_advance_push';

    public function project(array $ctx): void
    {
        /** @var PersonaNotificacion $notification */
        $notification = $ctx['notification'];
        /** @var PersonaNotificacionInteraccion $interaction */
        $interaction = $ctx['interaction'];
        $type = (string) ($ctx['interaction_type'] ?? $interaction->interaction_type);

        $context = $notification->decodeContext();
        $idTurno = (int) ($context['id_turno'] ?? 0);
        $offerId = (int) ($context['offer_id'] ?? 0);
        $campaignId = (int) ($context['campaign_id'] ?? 0);
        $anchor = (int) ($context['id_cancelled_turno'] ?? 0);
        if ($idTurno <= 0) {
            $data = $notification->decodeData();
            $idTurno = (int) ($data['id_turno'] ?? 0);
        }
        if ($idTurno <= 0) {
            return;
        }
        $turno = Turno::findActive()->andWhere(['id_turnos' => $idTurno])->one();
        if ($turno === null || (int) $turno->id_persona !== (int) $notification->id_persona) {
            return;
        }
        if ($anchor <= 0) {
            $anchor = $idTurno;
        }

        $ref = (string) ($notification->public_ref ?: ('id:' . (int) $notification->id));
        $meta = [
            'offer_id' => $offerId,
            'campaign_id' => $campaignId,
            'id_turno_candidate' => $idTurno,
            'public_ref' => $ref,
            'source' => $interaction->source,
            'client_event_id' => $interaction->client_event_id,
        ];

        if ($type === PersonaNotificacionInteraccion::TYPE_DELIVERED) {
            (new TurnoCanonicalEventService())->record(TurnoCanonicalEventCommand::create(
                $anchor,
                (int) $turno->id_persona,
                TurnoEventoAudit::EVENT_APPOINTMENT_ADVANCE_DELIVERED,
                TurnoEventoAudit::ACTOR_SISTEMA,
                'advance-delivered:' . $ref,
                TurnoEventoAudit::QUALITY_NATIVE,
                null,
                'push',
                'mobile_fcm_ack',
                null,
                $interaction->occurred_at,
                $meta
            ));
            return;
        }

        if ($type === PersonaNotificacionInteraccion::TYPE_OPENED) {
            $actor = isset($ctx['actor_type']) && is_string($ctx['actor_type'])
                && in_array($ctx['actor_type'], TurnoEventoAudit::actorTypeValues(), true)
                ? $ctx['actor_type']
                : TurnoEventoAudit::ACTOR_PACIENTE;
            $idUser = isset($ctx['id_user']) ? (int) $ctx['id_user'] : null;
            if ($idUser !== null && $idUser <= 0) {
                $idUser = null;
            }
            (new TurnoCanonicalEventService())->record(TurnoCanonicalEventCommand::create(
                $anchor,
                (int) $turno->id_persona,
                TurnoEventoAudit::EVENT_APPOINTMENT_ADVANCE_OPENED,
                $actor,
                'advance-opened:' . $ref,
                TurnoEventoAudit::QUALITY_NATIVE,
                $idUser,
                'push',
                'mobile_push_tap',
                null,
                $interaction->occurred_at,
                $meta
            ));
        }
    }
}
