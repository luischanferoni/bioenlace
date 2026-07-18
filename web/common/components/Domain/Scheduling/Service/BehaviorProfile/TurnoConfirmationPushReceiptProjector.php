<?php

namespace common\components\Domain\Scheduling\Service\BehaviorProfile;

use common\components\Domain\Scheduling\Service\TurnoConfirmationService;
use common\components\Platform\Core\Service\Notificaciones\PushNotificationReceiptProjectorInterface;
use common\models\PersonaNotificacion;
use common\models\PersonaNotificacionInteraccion;
use common\models\Scheduling\Turno;
use common\models\TurnoEventoAudit;

/**
 * Proyecta DELIVERED/OPENED de notificaciones de confirmación al stream canónico.
 */
final class TurnoConfirmationPushReceiptProjector implements PushNotificationReceiptProjectorInterface
{
    public const HANDLER_ID = 'scheduling.turno_confirmation_push';

    public function project(array $ctx): void
    {
        /** @var PersonaNotificacion $notification */
        $notification = $ctx['notification'];
        /** @var PersonaNotificacionInteraccion $interaction */
        $interaction = $ctx['interaction'];
        $type = (string) ($ctx['interaction_type'] ?? $interaction->interaction_type);
        $idUser = isset($ctx['id_user']) ? (int) $ctx['id_user'] : null;
        if ($idUser !== null && $idUser <= 0) {
            $idUser = null;
        }
        $actorHint = isset($ctx['actor_type']) && is_string($ctx['actor_type']) ? $ctx['actor_type'] : null;

        $context = $notification->decodeContext();
        $idTurno = (int) ($context['id_turno'] ?? 0);
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

        $ref = (string) $notification->public_ref;
        if ($ref === '') {
            $ref = 'id:' . (int) $notification->id;
        }

        if ($type === PersonaNotificacionInteraccion::TYPE_DELIVERED) {
            (new TurnoConfirmationService())->recordConfirmationDeliveryConfirmed(
                $turno,
                $ref,
                [
                    'id_persona_notificacion' => (int) $notification->id,
                    'public_ref' => $ref,
                    'source' => $interaction->source,
                    'provider_message_id' => $interaction->provider_message_id,
                    'client_event_id' => $interaction->client_event_id,
                ],
                $interaction->occurred_at
            );
            return;
        }

        if ($type === PersonaNotificacionInteraccion::TYPE_OPENED) {
            $actor = $actorHint !== null && in_array($actorHint, TurnoEventoAudit::actorTypeValues(), true)
                ? $actorHint
                : TurnoEventoAudit::ACTOR_PACIENTE;
            (new TurnoConfirmationService())->recordConfirmationOpened(
                $turno,
                $ref,
                $actor,
                $idUser,
                [
                    'id_persona_notificacion' => (int) $notification->id,
                    'public_ref' => $ref,
                    'source' => $interaction->source,
                    'provider_message_id' => $interaction->provider_message_id,
                    'client_event_id' => $interaction->client_event_id,
                ],
                $interaction->occurred_at
            );
        }
    }
}
