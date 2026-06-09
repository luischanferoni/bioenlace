<?php

namespace common\components\Person\Representation\Service;

use common\components\Person\Representation\Enum\DelegationConsentStatus;
use common\components\Person\Representation\Enum\PersonRelatedBlockedReason;
use common\components\Person\Representation\Enum\PersonRelatedStatus;
use common\components\Person\Representation\Enum\PersonRelatedVerifiedBy;
use common\components\Person\Representation\Enum\RepresentationRegime;
use common\models\Person\PersonDelegationConsent;
use common\models\Person\PersonRelated;
use common\models\Person\PersonRelatedAuditLog;
use common\models\Person\RelationshipType;
use common\models\Persona;
use Yii;

/**
 * Régimen A: tutela verificada (padre/madre/tutor legal opera por menor sin cuenta).
 */
final class VerifiedGuardianshipService
{
    private PersonRepresentationMpiService $mpiService;
    private RepresentationPermissionsCatalog $permissionsCatalog;
    private PersonRepresentationPresenter $presenter;

    public function __construct(
        ?PersonRepresentationMpiService $mpiService = null,
        ?RepresentationPermissionsCatalog $permissionsCatalog = null,
        ?PersonRepresentationPresenter $presenter = null
    ) {
        $this->mpiService = $mpiService ?? new PersonRepresentationMpiService();
        $this->permissionsCatalog = $permissionsCatalog ?? new RepresentationPermissionsCatalog();
        $this->presenter = $presenter ?? new PersonRepresentationPresenter();
    }

    /**
     * @param array<string, mixed> $input
     * @return array{success: true, data: array<string, mixed>}
     */
    public function solicitarMenorComoTutor(int $actorPersonaId, array $input): array
    {
        $this->assertActorWithAccount($actorPersonaId);

        $relationshipCode = trim((string) ($input['relationship_type_code'] ?? ''));
        if ($relationshipCode === '') {
            throw new \InvalidArgumentException('relationship_type_code es obligatorio (padre, madre, tutor_legal).');
        }

        $relationshipType = RelationshipType::findByCode($relationshipCode);
        if ($relationshipType === null) {
            throw new \InvalidArgumentException('Tipo de vínculo no válido.');
        }
        if (!in_array($relationshipType->regime_allowed, ['A', 'both'], true)) {
            throw new \InvalidArgumentException('Ese parentesco no aplica al régimen de tutela verificada.');
        }

        $subject = $this->mpiService->resolveOrCreateSubject($input);
        $this->assertSubjectEligibleForGuardianship($subject);

        if ((int) $subject->id_persona === $actorPersonaId) {
            throw new \InvalidArgumentException('No podés solicitar tutela sobre vos mismo.');
        }

        $evidence = $this->buildEvidencePayload($input, $relationshipType);
        if ($relationshipType->requires_legal_document && $evidence === []) {
            throw new \InvalidArgumentException('El tutor legal debe adjuntar documentación en evidence.');
        }

        $link = PersonRelated::findOne([
            'subject_persona_id' => (int) $subject->id_persona,
            'actor_persona_id' => $actorPersonaId,
            'regime' => RepresentationRegime::VERIFIED_GUARDIANSHIP,
        ]);

        $now = gmdate('Y-m-d H:i:s');
        if ($link === null) {
            $link = new PersonRelated();
            $link->subject_persona_id = (int) $subject->id_persona;
            $link->actor_persona_id = $actorPersonaId;
            $link->relationship_type_id = (int) $relationshipType->id;
            $link->regime = RepresentationRegime::VERIFIED_GUARDIANSHIP;
            $link->created_at = $now;
        } elseif (in_array($link->status, [PersonRelatedStatus::ACTIVE, PersonRelatedStatus::BLOCKED, PersonRelatedStatus::PENDING], true)) {
            throw new \InvalidArgumentException('Ya existe un vínculo en curso para este tutor y menor.');
        }

        $link->status = PersonRelatedStatus::PENDING;
        $link->verified_by = PersonRelatedVerifiedBy::NONE;
        $link->verified_at = null;
        $link->blocked_reason = null;
        $link->blocked_at = null;
        $link->blocked_by_user_id = null;
        $link->permissions_json = null;
        $link->evidence_json = $evidence !== [] ? json_encode($evidence, JSON_UNESCAPED_UNICODE) : null;
        $link->updated_at = $now;

        if (!$link->save()) {
            throw new \RuntimeException('No se pudo registrar la solicitud: ' . json_encode($link->getErrors()));
        }

        PersonRelatedAuditLog::record(
            PersonRelatedAuditLog::ACTION_LINK_REQUESTED,
            $actorPersonaId,
            (int) $subject->id_persona,
            (int) $link->id,
            $this->sessionUserId(),
            ['relationship_type_code' => $relationshipCode]
        );

        $link->refresh();

        return [
            'success' => true,
            'data' => [
                'vinculo' => $this->presenter->linkToArray($link),
                'mensaje' => 'Solicitud registrada. Un operador del centro debe verificar el vínculo.',
            ],
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{success: true, data: array<string, mixed>}
     */
    public function verificarVinculoParaStaff(int $staffUserId, array $input): array
    {
        $link = $this->requireGuardianshipLink((int) ($input['person_related_id'] ?? 0));
        if ($link->status !== PersonRelatedStatus::PENDING) {
            throw new \InvalidArgumentException('Solo se pueden verificar vínculos pendientes.');
        }

        $now = gmdate('Y-m-d H:i:s');
        $link->status = PersonRelatedStatus::ACTIVE;
        $link->verified_by = PersonRelatedVerifiedBy::STAFF;
        $link->verified_at = $now;
        $link->permissions_json = json_encode([
            'template_id' => 'representation_permissions_v1',
            'permissions' => $this->permissionsCatalog->getDefaultPermissions(),
        ], JSON_UNESCAPED_UNICODE);
        $link->updated_at = $now;

        $staffNote = trim((string) ($input['nota'] ?? ''));
        if ($staffNote !== '') {
            $link->evidence_json = $this->mergeEvidence($link, ['staff_verification_note' => $staffNote]);
        }

        if (!$link->save()) {
            throw new \RuntimeException('No se pudo verificar el vínculo: ' . json_encode($link->getErrors()));
        }

        PersonRelatedAuditLog::record(
            PersonRelatedAuditLog::ACTION_LINK_VERIFIED,
            (int) $link->actor_persona_id,
            (int) $link->subject_persona_id,
            (int) $link->id,
            $staffUserId,
            ['event' => 'verificado_staff']
        );

        $link->refresh();

        return [
            'success' => true,
            'data' => ['vinculo' => $this->presenter->linkToArray($link, true, true)],
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{success: true, data: array<string, mixed>}
     */
    public function bloquearParaStaff(int $staffUserId, array $input): array
    {
        $link = $this->requireLink((int) ($input['person_related_id'] ?? 0));
        if ($link->status === PersonRelatedStatus::REVOKED) {
            throw new \InvalidArgumentException('El vínculo ya está revocado.');
        }

        $reason = trim((string) ($input['blocked_reason'] ?? PersonRelatedBlockedReason::COURT_ORDER));
        if (!in_array($reason, [
            PersonRelatedBlockedReason::COURT_ORDER,
            PersonRelatedBlockedReason::CUSTODY_DISPUTE,
            PersonRelatedBlockedReason::OTHER,
        ], true)) {
            throw new \InvalidArgumentException('blocked_reason no válido.');
        }

        $now = gmdate('Y-m-d H:i:s');
        $link->status = PersonRelatedStatus::BLOCKED;
        $link->blocked_reason = $reason;
        $link->blocked_at = $now;
        $link->blocked_by_user_id = $staffUserId;
        $link->updated_at = $now;

        $staffNote = trim((string) ($input['nota'] ?? ''));
        if ($staffNote !== '') {
            $link->evidence_json = $this->mergeEvidence($link, ['block_note' => $staffNote]);
        }

        if (!$link->save()) {
            throw new \RuntimeException('No se pudo bloquear el vínculo: ' . json_encode($link->getErrors()));
        }

        PersonRelatedAuditLog::record(
            PersonRelatedAuditLog::ACTION_LINK_BLOCKED,
            (int) $link->actor_persona_id,
            (int) $link->subject_persona_id,
            (int) $link->id,
            $staffUserId,
            ['blocked_reason' => $reason]
        );

        $link->refresh();

        return [
            'success' => true,
            'data' => ['vinculo' => $this->presenter->linkToArray($link, true, true)],
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{success: true, data: array<string, mixed>}
     */
    public function revocarParaStaff(int $staffUserId, array $input): array
    {
        $link = $this->requireLink((int) ($input['person_related_id'] ?? 0));
        if ($link->status === PersonRelatedStatus::REVOKED) {
            throw new \InvalidArgumentException('El vínculo ya está revocado.');
        }

        $now = gmdate('Y-m-d H:i:s');
        $link->status = PersonRelatedStatus::REVOKED;
        $link->updated_at = $now;

        $staffNote = trim((string) ($input['nota'] ?? ''));
        if ($staffNote !== '') {
            $link->evidence_json = $this->mergeEvidence($link, ['revoke_note' => $staffNote]);
        }

        if (!$link->save()) {
            throw new \RuntimeException('No se pudo revocar el vínculo: ' . json_encode($link->getErrors()));
        }

        $this->revokeDelegationConsentIfAny($link, $now);

        PersonRelatedAuditLog::record(
            PersonRelatedAuditLog::ACTION_LINK_REVOKED,
            (int) $link->actor_persona_id,
            (int) $link->subject_persona_id,
            (int) $link->id,
            $staffUserId,
            ['event' => 'revocado_staff']
        );

        $link->refresh();

        return [
            'success' => true,
            'data' => ['vinculo' => $this->presenter->linkToArray($link, true, true)],
        ];
    }

    /**
     * @return array{success: true, data: array{vinculos: list<array<string, mixed>>}}
     */
    public function listarMisVinculosComoTutor(int $actorPersonaId, ?string $statusFilter = null): array
    {
        $query = PersonRelated::find()
            ->where([
                'actor_persona_id' => $actorPersonaId,
                'regime' => RepresentationRegime::VERIFIED_GUARDIANSHIP,
            ])
            ->with('relationshipType')
            ->orderBy(['updated_at' => SORT_DESC]);

        if ($statusFilter !== null && trim($statusFilter) !== '') {
            $query->andWhere(['status' => trim($statusFilter)]);
        } else {
            $query->andWhere(['status' => [
                PersonRelatedStatus::PENDING,
                PersonRelatedStatus::ACTIVE,
                PersonRelatedStatus::BLOCKED,
            ]]);
        }

        $rows = [];
        foreach ($query->all() as $link) {
            $rows[] = $this->presenter->linkToArray($link);
        }

        return ['success' => true, 'data' => ['vinculos' => $rows]];
    }

    /**
     * @return array{success: true, data: array{vinculos: list<array<string, mixed>>}}
     */
    public function listarVinculosPacienteParaStaff(int $subjectPersonaId): array
    {
        if ($subjectPersonaId <= 0) {
            throw new \InvalidArgumentException('id_persona es obligatorio.');
        }

        $query = PersonRelated::find()
            ->where(['subject_persona_id' => $subjectPersonaId])
            ->with('relationshipType')
            ->orderBy(['updated_at' => SORT_DESC]);

        $rows = [];
        foreach ($query->all() as $link) {
            $rows[] = $this->presenter->linkToArray($link, false, true);
        }

        return ['success' => true, 'data' => ['vinculos' => $rows]];
    }

    private function requireGuardianshipLink(int $personRelatedId): PersonRelated
    {
        $link = $this->requireLink($personRelatedId);
        if ($link->regime !== RepresentationRegime::VERIFIED_GUARDIANSHIP) {
            throw new \InvalidArgumentException('El vínculo no pertenece al régimen de tutela verificada.');
        }

        return $link;
    }

    private function requireLink(int $personRelatedId): PersonRelated
    {
        if ($personRelatedId <= 0) {
            throw new \InvalidArgumentException('person_related_id es obligatorio.');
        }
        $link = PersonRelated::find()->where(['id' => $personRelatedId])->with('relationshipType')->one();
        if ($link === null) {
            throw new \InvalidArgumentException('Vínculo no encontrado.');
        }

        return $link;
    }

    private function assertActorWithAccount(int $actorPersonaId): void
    {
        if ($actorPersonaId <= 0) {
            throw new \InvalidArgumentException('Sesión sin persona.');
        }
        $actor = Persona::findOne($actorPersonaId);
        if ($actor === null || (int) $actor->id_user <= 0) {
            throw new \InvalidArgumentException('Solo una persona con cuenta puede solicitar tutela.');
        }
    }

    private function assertSubjectEligibleForGuardianship(Persona $subject): void
    {
        if ((int) $subject->id_user > 0) {
            throw new \InvalidArgumentException('El menor no debe tener cuenta propia para tutela verificada.');
        }
        if ($subject->fecha_nacimiento !== null && trim((string) $subject->fecha_nacimiento) !== '') {
            if ((int) $subject->getEdad() >= 18) {
                throw new \InvalidArgumentException('El sujeto no es menor de edad.');
            }
        }
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    private function buildEvidencePayload(array $input, RelationshipType $relationshipType): array
    {
        $evidence = $input['evidence'] ?? $input['evidence_json'] ?? null;
        if (is_string($evidence) && trim($evidence) !== '') {
            $decoded = json_decode($evidence, true);

            return is_array($decoded) ? $decoded : [];
        }

        return is_array($evidence) ? $evidence : [];
    }

    /**
     * @param array<string, mixed> $extra
     */
    private function mergeEvidence(PersonRelated $link, array $extra): string
    {
        $base = [];
        if ($link->evidence_json !== null && trim((string) $link->evidence_json) !== '') {
            $decoded = json_decode((string) $link->evidence_json, true);
            if (is_array($decoded)) {
                $base = $decoded;
            }
        }

        return json_encode(array_merge($base, $extra), JSON_UNESCAPED_UNICODE);
    }

    private function revokeDelegationConsentIfAny(PersonRelated $link, string $now): void
    {
        if ($link->regime !== RepresentationRegime::PATIENT_DELEGATION) {
            return;
        }
        $consent = PersonDelegationConsent::findActiveForLink((int) $link->id);
        if ($consent === null) {
            return;
        }
        $consent->status = DelegationConsentStatus::REVOKED;
        $consent->revoked_at = $now;
        $consent->updated_at = $now;
        $consent->save(false);
    }

    private function sessionUserId(): ?int
    {
        $id = Yii::$app->user->id ?? null;

        return $id !== null ? (int) $id : null;
    }
}
