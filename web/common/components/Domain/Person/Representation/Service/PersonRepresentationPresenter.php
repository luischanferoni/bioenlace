<?php

namespace common\components\Domain\Person\Representation\Service;

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
     * Ítem de lista UI staff: solicitud de tutela pending (menor + solicitante + vínculo).
     *
     * @param array<string, mixed> $row salida de {@see linkToArray} con actor y subject
     * @return array<string, mixed>|null
     */
    public function pendingGuardianshipStaffListItem(array $row): ?array
    {
        $id = (int) ($row['id'] ?? 0);
        if ($id <= 0) {
            return null;
        }

        $actor = is_array($row['actor'] ?? null) ? $row['actor'] : [];
        $subject = is_array($row['subject'] ?? null) ? $row['subject'] : [];
        $rel = is_array($row['relationship_type'] ?? null) ? $row['relationship_type'] : [];

        $menorLabel = $this->personaDisplayLabel($subject);
        if ($menorLabel === '') {
            $menorLabel = 'Menor sin nombre';
        }

        $tutorLabel = $this->personaDisplayLabel($actor);
        if ($tutorLabel === '') {
            $tutorLabel = 'Solicitante';
        }

        $menorDoc = trim((string) ($subject['documento'] ?? ''));
        $tutorDoc = trim((string) ($actor['documento'] ?? ''));
        $relLabel = trim((string) ($rel['label'] ?? $rel['code'] ?? 'Tutela'));
        $edad = $subject['edad'] ?? null;
        $fnac = trim((string) ($subject['fecha_nacimiento'] ?? ''));
        $createdAt = trim((string) ($row['created_at'] ?? ''));

        $menorParts = [];
        if ($menorDoc !== '') {
            $menorParts[] = 'DNI ' . $menorDoc;
        }
        if ($edad !== null && (int) $edad >= 0) {
            $menorParts[] = (int) $edad . ' años';
        }
        if ($fnac !== '') {
            $menorParts[] = 'nac. ' . $this->formatDateForDisplay($fnac);
        }

        $subtitleParts = [];
        if ($menorParts !== []) {
            $subtitleParts[] = implode(' · ', $menorParts);
        }
        $subtitleParts[] = 'Solicita: ' . $tutorLabel . ($tutorDoc !== '' ? ' (DNI ' . $tutorDoc . ')' : '');
        $subtitleParts[] = 'Vínculo: ' . $relLabel;
        if ($createdAt !== '') {
            $subtitleParts[] = 'Pedido: ' . $this->formatDateForDisplay($createdAt);
        }

        return [
            'id' => (string) $id,
            'name' => $menorLabel,
            'label' => $menorLabel,
            'subtitle' => implode(' · ', $subtitleParts),
            'meta' => [
                'person_related_id' => $id,
                'menor_nombre' => $menorLabel,
                'menor_documento' => $menorDoc,
                'menor_edad' => $edad !== null ? (int) $edad : null,
                'menor_fecha_nacimiento' => $fnac !== '' ? $fnac : null,
                'tutor_nombre' => $tutorLabel,
                'tutor_documento' => $tutorDoc,
                'parentesco' => $relLabel,
                'parentesco_code' => trim((string) ($rel['code'] ?? '')),
                'status' => (string) ($row['status'] ?? 'pending'),
                'created_at' => $createdAt !== '' ? $createdAt : null,
            ],
        ];
    }

    /**
     * @param array<string, mixed>|null $persona
     */
    private function personaDisplayLabel(?array $persona): string
    {
        if ($persona === null || $persona === []) {
            return '';
        }
        $apellido = trim((string) ($persona['apellido'] ?? ''));
        $nombre = trim((string) ($persona['nombre'] ?? ''));
        $label = trim($apellido . ', ' . $nombre);

        return $label !== ',' ? $label : '';
    }

    private function formatDateForDisplay(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $value, $m) === 1) {
            return $m[3] . '/' . $m[2] . '/' . $m[1];
        }

        return $value;
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
