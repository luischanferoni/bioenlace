<?php

namespace common\components\Domain\Person\Representation\Application\Service;

use common\components\Domain\Person\Representation\Application\Catalog\PersonRepresentationDelegatedActionNotificationCatalog;
use common\components\Platform\Core\Service\Push\PushNotificationSender;
use common\components\Platform\Core\Service\Push\PushNotificationTypes;
use common\models\Person\Persona;

/**
 * Notificación opcional al paciente (N9) cuando un representante actúa en su nombre.
 */
final class PersonRepresentationDelegatedActionService
{
    private PersonRepresentationPreferenceService $preferenceService;
    private PersonRepresentationDelegatedActionNotificationCatalog $notificationCatalog;

    public function __construct(
        ?PersonRepresentationPreferenceService $preferenceService = null,
        ?PersonRepresentationDelegatedActionNotificationCatalog $notificationCatalog = null
    ) {
        $this->preferenceService = $preferenceService ?? new PersonRepresentationPreferenceService();
        $this->notificationCatalog = $notificationCatalog ?? new PersonRepresentationDelegatedActionNotificationCatalog();
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function notifyIfEnabled(
        string $action,
        int $actorPersonaId,
        int $subjectPersonaId,
        array $payload = []
    ): void {
        if ($actorPersonaId <= 0 || $subjectPersonaId <= 0 || $actorPersonaId === $subjectPersonaId) {
            return;
        }
        if (!$this->preferenceService->shouldNotifyOnRepresentativeAction($subjectPersonaId)) {
            return;
        }

        $actorLabel = $this->actorLabel($actorPersonaId);
        $message = $this->notificationCatalog->forAction($action);
        $body = str_replace('{actor_label}', $actorLabel, $message['body_template']);

        $data = [
            'type' => PushNotificationTypes::REPRESENTATIVE_ACTION,
            'action' => $action,
            'actor_persona_id' => $actorPersonaId,
            'subject_persona_id' => $subjectPersonaId,
        ];
        foreach ($payload as $key => $value) {
            if (is_scalar($value)) {
                $data[(string) $key] = $value;
            }
        }

        (new PushNotificationSender())->sendToPersona($subjectPersonaId, $data, $message['title'], $body);
    }

    private function actorLabel(int $actorPersonaId): string
    {
        $persona = Persona::findOne($actorPersonaId);
        if ($persona === null) {
            return 'Tu representante';
        }
        $name = trim((string) $persona->nombre . ' ' . (string) $persona->apellido);
        return $name !== '' ? $name : 'Tu representante';
    }
}
