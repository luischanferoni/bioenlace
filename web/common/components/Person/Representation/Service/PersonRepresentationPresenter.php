<?php

namespace common\components\Person\Representation\Service;

use common\models\Person\PersonDelegationConsent;
use common\models\Person\PersonRelated;
use common\models\Person\Persona;

/**
 * Serialización API de vínculos de representación.
 */
final class PersonRepresentationPresenter
{
    /**
     * @return array<string, mixed>
     */
    public function linkToArray(PersonRelated $link, bool $includeSubject = true, bool $includeActor = false): array
    {
        $relationship = $link->relationshipType;
        $out = [
            'id' => (int) $link->id,
            'subject_persona_id' => (int) $link->subject_persona_id,
            'actor_persona_id' => (int) $link->actor_persona_id,
            'relationship_type' => $relationship !== null ? [
                'code' => (string) $relationship->code,
                'label' => (string) $relationship->label,
            ] : null,
            'regime' => (string) $link->regime,
            'status' => (string) $link->status,
            'verified_by' => (string) $link->verified_by,
            'verified_at' => $link->verified_at,
            'blocked_reason' => $link->blocked_reason,
            'blocked_at' => $link->blocked_at,
            'permissions' => $link->getPermissionsList(),
            'created_at' => $link->created_at,
            'updated_at' => $link->updated_at,
        ];

        if ($includeSubject) {
            $out['subject'] = $this->personaSummary((int) $link->subject_persona_id);
        }
        if ($includeActor) {
            $out['actor'] = $this->personaSummary((int) $link->actor_persona_id);
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    public function delegationLinkToArray(
        PersonRelated $link,
        ?PersonDelegationConsent $consent = null,
        bool $includeSubject = true,
        bool $includeActor = false
    ): array {
        $out = $this->linkToArray($link, $includeSubject, $includeActor);
        $out['consent'] = $consent !== null ? [
            'id' => (int) $consent->id,
            'status' => (string) $consent->status,
            'granted_at' => $consent->granted_at,
            'revoked_at' => $consent->revoked_at,
            'permissions' => $consent->getProvisionPermissionsList(),
        ] : null;

        return $out;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function personaSummary(int $idPersona): ?array
    {
        if ($idPersona <= 0) {
            return null;
        }
        $persona = Persona::findOne($idPersona);
        if ($persona === null) {
            return null;
        }

        return [
            'id_persona' => (int) $persona->id_persona,
            'nombre' => (string) $persona->nombre,
            'apellido' => (string) $persona->apellido,
            'documento' => (string) $persona->documento,
            'fecha_nacimiento' => $persona->fecha_nacimiento,
            'edad' => $persona->fecha_nacimiento ? (int) $persona->getEdad() : null,
            'tiene_cuenta' => (int) $persona->id_user > 0,
        ];
    }
}
