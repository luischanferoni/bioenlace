<?php

namespace common\components\Platform\Core\Service\Notificaciones;

use common\models\PersonaNotificacion;
use common\models\PersonaNotificacionInteraccion;
use common\models\TurnoEventoAudit;
use Yii;
use yii\db\IntegrityException;

/**
 * Registra interacciones push autenticadas (DELIVERED / OPENED) de forma idempotente.
 */
final class PersonaNotificacionInteractionService
{
    /**
     * @param array{
     *   notification_ref: string,
     *   interaction_type: string,
     *   client_event_id: string,
     *   source?: string|null,
     *   provider_message_id?: string|null,
     *   occurred_at?: string|null,
     *   actor_type?: string|null
     * } $payload
     * @return array{created: bool, interaction_id: int, notification_ref: string, interaction_type: string}
     */
    public function registerOwn(int $idPersona, array $payload, ?int $idUser = null): array
    {
        $ref = trim((string) ($payload['notification_ref'] ?? ''));
        $type = strtoupper(trim((string) ($payload['interaction_type'] ?? '')));
        $clientEventId = trim((string) ($payload['client_event_id'] ?? ''));
        if ($ref === '' || $clientEventId === '') {
            throw new \InvalidArgumentException('notification_ref y client_event_id son obligatorios.');
        }
        if (!in_array($type, PersonaNotificacionInteraccion::typeValues(), true)) {
            throw new \InvalidArgumentException('interaction_type inválido.');
        }

        $notification = PersonaNotificacion::findOne(['public_ref' => $ref]);
        if ($notification === null || (int) $notification->id_persona !== $idPersona) {
            throw new \InvalidArgumentException('Notificación no encontrada.');
        }

        $existing = PersonaNotificacionInteraccion::findOne([
            'id_persona_notificacion' => (int) $notification->id,
            'interaction_type' => $type,
            'client_event_id' => $clientEventId,
        ]);
        if ($existing !== null) {
            return [
                'created' => false,
                'interaction_id' => (int) $existing->id,
                'notification_ref' => $ref,
                'interaction_type' => $type,
            ];
        }

        $occurredAt = isset($payload['occurred_at']) ? trim((string) $payload['occurred_at']) : '';
        if ($occurredAt === '' || strtotime($occurredAt) === false) {
            $occurredAt = date('Y-m-d H:i:s');
        }

        $row = new PersonaNotificacionInteraccion();
        $row->id_persona_notificacion = (int) $notification->id;
        $row->id_persona = $idPersona;
        $row->interaction_type = $type;
        $row->client_event_id = $clientEventId;
        $row->source = isset($payload['source']) ? substr(trim((string) $payload['source']), 0, 64) : null;
        $row->provider_message_id = isset($payload['provider_message_id'])
            ? substr(trim((string) $payload['provider_message_id']), 0, 191)
            : null;
        $row->occurred_at = $occurredAt;

        try {
            $row->save(false);
        } catch (IntegrityException $e) {
            $existing = PersonaNotificacionInteraccion::findOne([
                'id_persona_notificacion' => (int) $notification->id,
                'interaction_type' => $type,
                'client_event_id' => $clientEventId,
            ]);
            if ($existing !== null) {
                return [
                    'created' => false,
                    'interaction_id' => (int) $existing->id,
                    'notification_ref' => $ref,
                    'interaction_type' => $type,
                ];
            }
            throw $e;
        }

        $this->projectIfNeeded($notification, $row, $idUser, $payload['actor_type'] ?? null);

        return [
            'created' => true,
            'interaction_id' => (int) $row->id,
            'notification_ref' => $ref,
            'interaction_type' => $type,
        ];
    }

    private function projectIfNeeded(
        PersonaNotificacion $notification,
        PersonaNotificacionInteraccion $interaction,
        ?int $idUser,
        $actorType
    ): void {
        $handlerId = trim((string) ($notification->context_handler_id ?? ''));
        if ($handlerId === '') {
            return;
        }
        $projector = PushNotificationReceiptProjectorRegistry::get($handlerId);
        if ($projector === null) {
            Yii::warning('Push receipt projector desconocido: ' . $handlerId, 'push-receipt');
            return;
        }
        $actor = is_string($actorType) && in_array($actorType, TurnoEventoAudit::actorTypeValues(), true)
            ? $actorType
            : null;
        $projector->project([
            'notification' => $notification,
            'interaction' => $interaction,
            'interaction_type' => $interaction->interaction_type,
            'id_user' => $idUser,
            'actor_type' => $actor,
        ]);
    }
}
