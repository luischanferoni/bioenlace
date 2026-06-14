<?php

namespace common\components\Domain\Person\Representation\Service;

use common\components\Platform\Core\Service\Push\PushNotificationSender;
use common\components\Platform\Core\Service\Push\PushNotificationTypes;
use common\models\Person\PersonRelatedAuditLog;
use common\models\Person\Persona;

/**
 * Notificación opcional al paciente (N9) cuando un representante actúa en su nombre.
 */
final class PersonRepresentationDelegatedActionNotifier
{
    private PersonRepresentationPreferenceService $preferenceService;

    public function __construct(?PersonRepresentationPreferenceService $preferenceService = null)
    {
        $this->preferenceService = $preferenceService ?? new PersonRepresentationPreferenceService();
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
        [$title, $body] = $this->messageFor($action, $actorLabel);

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

        (new PushNotificationSender())->sendToPersona($subjectPersonaId, $data, $title, $body);
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

    /**
     * @return array{0: string, 1: string}
     */
    private function messageFor(string $action, string $actorLabel): array
    {
        return match ($action) {
            PersonRelatedAuditLog::ACTION_TURNO_CREATED => [
                'Acción de tu representante',
                "$actorLabel sacó un turno a tu nombre.",
            ],
            PersonRelatedAuditLog::ACTION_TURNO_CANCELLED => [
                'Acción de tu representante',
                "$actorLabel canceló un turno a tu nombre.",
            ],
            PersonRelatedAuditLog::ACTION_MOTIVOS_SENT => [
                'Acción de tu representante',
                "$actorLabel cargó motivos de consulta a tu nombre.",
            ],
            PersonRelatedAuditLog::ACTION_CARE_PACK_ASSISTANCE => [
                'Acción de tu representante',
                "$actorLabel completó el cuestionario pre-consulta a tu nombre.",
            ],
            PersonRelatedAuditLog::ACTION_CARE_PACK_FOLLOWUP => [
                'Acción de tu representante',
                "$actorLabel registró el seguimiento post-consulta a tu nombre.",
            ],
            PersonRelatedAuditLog::ACTION_HISTORIA_ACCESSED => [
                'Acción de tu representante',
                "$actorLabel consultó tu historia clínica.",
            ],
            PersonRelatedAuditLog::ACTION_CARE_PLAN_ACCESSED => [
                'Acción de tu representante',
                "$actorLabel consultó tu plan de tratamiento.",
            ],
            default => [
                'Acción de tu representante',
                "$actorLabel realizó una gestión a tu nombre.",
            ],
        };
    }
}
