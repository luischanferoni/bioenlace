<?php

namespace common\components\Domain\Person\Representation\Service;

use common\components\Domain\Person\Representation\Enum\DelegationConsentStatus;
use common\components\Domain\Person\Representation\Enum\PersonRelatedStatus;
use common\components\Domain\Person\Representation\Enum\RepresentationRegime;
use common\models\Person\PersonDelegationConsent;
use common\models\Person\PersonRelated;
use common\models\Person\Persona;

/**
 * Resuelve a quién enviar notificaciones push de producto paciente cuando el sujeto
 * puede no tener cuenta (menor) o tener representantes activos (delegación).
 */
final class PersonRepresentationNotifyRecipientService
{
    private RepresentationPermissionsCatalog $permissionsCatalog;

    public function __construct(?RepresentationPermissionsCatalog $permissionsCatalog = null)
    {
        $this->permissionsCatalog = $permissionsCatalog ?? new RepresentationPermissionsCatalog();
    }

    /**
     * Personas con cuenta que deben recibir la notificación.
     *
     * - Sujeto con `id_user` y permiso sobre sí mismo (siempre true para el propio sujeto).
     * - Todos los actores (tutores verificados o representantes B) con el permiso v1 indicado.
     *
     * @return list<int>
     */
    public function resolvePushRecipientPersonaIds(int $subjectPersonaId, string $permission): array
    {
        if ($subjectPersonaId <= 0) {
            return [];
        }

        $recipients = [];

        $subject = Persona::findOne($subjectPersonaId);
        if ($subject !== null && (int) $subject->id_user > 0) {
            $recipients[] = $subjectPersonaId;
        }

        foreach ($this->listActiveLinksForSubject($subjectPersonaId) as $link) {
            $consent = null;
            if ($link->regime === RepresentationRegime::PATIENT_DELEGATION) {
                $consent = PersonDelegationConsent::findActiveForLink((int) $link->id);
            }
            if (!PersonRepresentationAccessService::evaluateAccess(
                $link,
                $consent,
                $permission,
                $this->permissionsCatalog
            )) {
                continue;
            }
            $actorId = (int) $link->actor_persona_id;
            if ($actorId > 0) {
                $recipients[] = $actorId;
            }
        }

        return array_values(array_unique($recipients));
    }

    public function subjectDisplayLabel(int $subjectPersonaId): string
    {
        $persona = Persona::findOne($subjectPersonaId);
        if ($persona === null) {
            return 'el paciente';
        }
        $name = trim((string) $persona->nombre . ' ' . (string) $persona->apellido);

        return $name !== '' ? $name : 'el paciente';
    }

    /**
     * @return list<PersonRelated>
     */
    private function listActiveLinksForSubject(int $subjectPersonaId): array
    {
        $rows = PersonRelated::find()
            ->where([
                'subject_persona_id' => $subjectPersonaId,
                'status' => PersonRelatedStatus::ACTIVE,
            ])
            ->all();

        $out = [];
        foreach ($rows as $link) {
            if ($link instanceof PersonRelated) {
                $out[] = $link;
            }
        }

        return $out;
    }
}
