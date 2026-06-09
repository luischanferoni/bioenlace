<?php

namespace common\components\Person\Representation\Service;

use common\components\Person\Representation\Enum\DelegationConsentStatus;
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
 * Régimen B: delegación paciente → representante (sin aceptación; Consent activo al designar).
 */
final class PatientDelegationService
{
    private RepresentationPermissionsCatalog $permissionsCatalog;
    private PersonRepresentationPresenter $presenter;
    private PersonRepresentationPreferenceService $preferenceService;

    public function __construct(
        ?RepresentationPermissionsCatalog $permissionsCatalog = null,
        ?PersonRepresentationPresenter $presenter = null,
        ?PersonRepresentationPreferenceService $preferenceService = null
    ) {
        $this->permissionsCatalog = $permissionsCatalog ?? new RepresentationPermissionsCatalog();
        $this->presenter = $presenter ?? new PersonRepresentationPresenter();
        $this->preferenceService = $preferenceService ?? new PersonRepresentationPreferenceService();
    }

    /**
     * @param array<string, mixed> $input
     * @return array{success: true, data: array<string, mixed>}
     */
    public function designarRepresentante(int $subjectPersonaId, array $input): array
    {
        $this->assertPersonaWithAccount($subjectPersonaId, 'Solo un paciente con cuenta puede designar representantes.');

        $representative = $this->resolveRepresentative($input);
        if ((int) $representative->id_persona === $subjectPersonaId) {
            throw new \InvalidArgumentException('No podés designarte a vos mismo como representante.');
        }

        $relationshipCode = trim((string) ($input['relationship_type_code'] ?? 'otro'));
        $relationshipType = RelationshipType::findByCode($relationshipCode);
        if ($relationshipType === null) {
            throw new \InvalidArgumentException('Tipo de vínculo no válido.');
        }
        if (!in_array($relationshipType->regime_allowed, ['B', 'both'], true)) {
            throw new \InvalidArgumentException('Ese parentesco no aplica a delegación de representante.');
        }

        $permissions = $this->resolvePermissions($input);
        $provisionJson = json_encode([
            'template_id' => 'representation_permissions_v1',
            'permissions' => $permissions,
        ], JSON_UNESCAPED_UNICODE);

        $now = gmdate('Y-m-d H:i:s');
        $link = PersonRelated::findOne([
            'subject_persona_id' => $subjectPersonaId,
            'actor_persona_id' => (int) $representative->id_persona,
            'regime' => RepresentationRegime::PATIENT_DELEGATION,
        ]);

        if ($link !== null && in_array($link->status, [PersonRelatedStatus::ACTIVE, PersonRelatedStatus::BLOCKED], true)) {
            throw new \InvalidArgumentException('Esa persona ya es representante activo o está bloqueada.');
        }

        if ($link === null) {
            $link = new PersonRelated();
            $link->subject_persona_id = $subjectPersonaId;
            $link->actor_persona_id = (int) $representative->id_persona;
            $link->relationship_type_id = (int) $relationshipType->id;
            $link->regime = RepresentationRegime::PATIENT_DELEGATION;
            $link->created_at = $now;
        }

        $link->status = PersonRelatedStatus::ACTIVE;
        $link->verified_by = PersonRelatedVerifiedBy::NONE;
        $link->verified_at = null;
        $link->blocked_reason = null;
        $link->blocked_at = null;
        $link->blocked_by_user_id = null;
        $link->permissions_json = $provisionJson;
        $link->updated_at = $now;

        $tx = Yii::$app->db->beginTransaction();
        try {
            if (!$link->save()) {
                throw new \RuntimeException('No se pudo designar al representante: ' . json_encode($link->getErrors()));
            }

            $this->revokeActiveConsents((int) $link->id, $now);
            $consent = new PersonDelegationConsent();
            $consent->person_related_id = (int) $link->id;
            $consent->status = DelegationConsentStatus::ACTIVE;
            $consent->granted_at = $now;
            $consent->revoked_at = null;
            $consent->provision_json = $provisionJson;
            $consent->created_at = $now;
            $consent->updated_at = $now;
            if (!$consent->save()) {
                throw new \RuntimeException('No se pudo registrar el consentimiento: ' . json_encode($consent->getErrors()));
            }

            PersonRelatedAuditLog::record(
                PersonRelatedAuditLog::ACTION_DELEGATION_DESIGNATED,
                (int) $representative->id_persona,
                $subjectPersonaId,
                (int) $link->id,
                $this->sessionUserId(),
                ['relationship_type_code' => $relationshipCode]
            );

            $tx->commit();
        } catch (\Throwable $e) {
            $tx->rollBack();
            throw $e;
        }

        $link->refresh();
        $consent = PersonDelegationConsent::findActiveForLink((int) $link->id);

        return [
            'success' => true,
            'data' => [
                'vinculo' => $this->presenter->delegationLinkToArray($link, $consent, true, true),
                'mensaje' => 'Representante designado. Puede operar de inmediato.',
            ],
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{success: true, data: array<string, mixed>}
     */
    public function revocarRepresentante(int $subjectPersonaId, array $input): array
    {
        $link = $this->resolveDelegationLinkForPatient($subjectPersonaId, $input);
        if ($link->status === PersonRelatedStatus::REVOKED) {
            throw new \InvalidArgumentException('El representante ya está revocado.');
        }

        $now = gmdate('Y-m-d H:i:s');
        $link->status = PersonRelatedStatus::REVOKED;
        $link->updated_at = $now;

        $tx = Yii::$app->db->beginTransaction();
        try {
            if (!$link->save()) {
                throw new \RuntimeException('No se pudo revocar: ' . json_encode($link->getErrors()));
            }
            $this->revokeActiveConsents((int) $link->id, $now);

            PersonRelatedAuditLog::record(
                PersonRelatedAuditLog::ACTION_DELEGATION_REVOKED,
                (int) $link->actor_persona_id,
                $subjectPersonaId,
                (int) $link->id,
                $this->sessionUserId(),
                ['event' => 'revocado_paciente']
            );

            $tx->commit();
        } catch (\Throwable $e) {
            $tx->rollBack();
            throw $e;
        }

        $link->refresh();

        return [
            'success' => true,
            'data' => [
                'vinculo' => $this->presenter->delegationLinkToArray($link, null, true, true),
            ],
        ];
    }

    /**
     * @return array{success: true, data: array{representantes: list<array<string, mixed>>, preferencias: array<string, mixed>}}
     */
    public function listarMisRepresentantes(int $subjectPersonaId): array
    {
        if ($subjectPersonaId <= 0) {
            throw new \InvalidArgumentException('Sesión sin persona.');
        }

        $query = PersonRelated::find()
            ->where([
                'subject_persona_id' => $subjectPersonaId,
                'regime' => RepresentationRegime::PATIENT_DELEGATION,
            ])
            ->with(['relationshipType', 'activeConsent'])
            ->orderBy(['updated_at' => SORT_DESC]);

        $rows = [];
        foreach ($query->all() as $link) {
            $rows[] = $this->presenter->delegationLinkToArray($link, $link->activeConsent, false, true);
        }

        return [
            'success' => true,
            'data' => [
                'representantes' => $rows,
                'preferencias' => $this->preferenceService->getForPersona($subjectPersonaId),
            ],
        ];
    }

    /**
     * @return array{success: true, data: array{pacientes: list<array<string, mixed>>}}
     */
    public function listarPacientesACargo(int $actorPersonaId): array
    {
        if ($actorPersonaId <= 0) {
            throw new \InvalidArgumentException('Sesión sin persona.');
        }

        $query = PersonRelated::find()
            ->alias('pr')
            ->innerJoin(
                ['c' => PersonDelegationConsent::tableName()],
                'c.person_related_id = pr.id AND c.status = :consentActive',
                [':consentActive' => DelegationConsentStatus::ACTIVE]
            )
            ->where([
                'pr.actor_persona_id' => $actorPersonaId,
                'pr.regime' => RepresentationRegime::PATIENT_DELEGATION,
                'pr.status' => PersonRelatedStatus::ACTIVE,
            ])
            ->with('relationshipType')
            ->orderBy(['pr.updated_at' => SORT_DESC]);

        $rows = [];
        foreach ($query->all() as $link) {
            $consent = PersonDelegationConsent::findActiveForLink((int) $link->id);
            $rows[] = $this->presenter->delegationLinkToArray($link, $consent, true, false);
        }

        return [
            'success' => true,
            'data' => ['pacientes' => $rows],
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{success: true, data: array<string, mixed>}
     */
    public function guardarPreferencias(int $subjectPersonaId, array $input): array
    {
        $prefs = $this->preferenceService->saveForPersona($subjectPersonaId, $input);

        return ['success' => true, 'data' => ['preferencias' => $prefs]];
    }

    /**
     * @return array{success: true, data: array<string, mixed>}
     */
    public function obtenerPreferencias(int $subjectPersonaId): array
    {
        return [
            'success' => true,
            'data' => ['preferencias' => $this->preferenceService->getForPersona($subjectPersonaId)],
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @return list<string>
     */
    private function resolvePermissions(array $input): array
    {
        $raw = $input['permissions'] ?? null;
        if (!is_array($raw) || $raw === []) {
            return $this->permissionsCatalog->getDefaultPermissions();
        }

        $permissions = array_values(array_filter(array_map('strval', $raw)));
        foreach ($permissions as $permission) {
            if (!$this->permissionsCatalog->isKnownPermission($permission)) {
                throw new \InvalidArgumentException('Permiso no válido: ' . $permission);
            }
        }

        return $permissions;
    }

    /**
     * @param array<string, mixed> $input
     */
    private function resolveRepresentative(array $input): Persona
    {
        $idPersona = (int) ($input['representative_id_persona'] ?? $input['actor_id_persona'] ?? $input['id_persona_representante'] ?? 0);
        if ($idPersona > 0) {
            $persona = Persona::findOne($idPersona);
            if ($persona === null) {
                throw new \InvalidArgumentException('Representante no encontrado.');
            }
            if ((int) $persona->id_user <= 0) {
                throw new \InvalidArgumentException('El representante debe tener cuenta en la app.');
            }

            return $persona;
        }

        $documento = trim((string) ($input['representative_documento'] ?? $input['documento_representante'] ?? ''));
        if ($documento === '') {
            throw new \InvalidArgumentException('Indicá representative_id_persona o representative_documento.');
        }

        $persona = Persona::findOne(['documento' => $documento]);
        if ($persona === null) {
            throw new \InvalidArgumentException('No hay persona registrada con ese documento.');
        }
        if ((int) $persona->id_user <= 0) {
            throw new \InvalidArgumentException('Esa persona no tiene cuenta; no puede ser representante.');
        }

        return $persona;
    }

    /**
     * @param array<string, mixed> $input
     */
    private function resolveDelegationLinkForPatient(int $subjectPersonaId, array $input): PersonRelated
    {
        $personRelatedId = (int) ($input['person_related_id'] ?? 0);
        if ($personRelatedId > 0) {
            $link = PersonRelated::find()
                ->where(['id' => $personRelatedId, 'subject_persona_id' => $subjectPersonaId])
                ->with('relationshipType')
                ->one();
            if ($link === null) {
                throw new \InvalidArgumentException('Vínculo no encontrado.');
            }
            if ($link->regime !== RepresentationRegime::PATIENT_DELEGATION) {
                throw new \InvalidArgumentException('El vínculo no es de delegación paciente.');
            }

            return $link;
        }

        $actorId = (int) ($input['representative_id_persona'] ?? $input['actor_id_persona'] ?? 0);
        if ($actorId <= 0) {
            throw new \InvalidArgumentException('Indicá person_related_id o representative_id_persona.');
        }

        $link = PersonRelated::findOne([
            'subject_persona_id' => $subjectPersonaId,
            'actor_persona_id' => $actorId,
            'regime' => RepresentationRegime::PATIENT_DELEGATION,
        ]);
        if ($link === null) {
            throw new \InvalidArgumentException('No existe delegación con ese representante.');
        }

        return $link;
    }

    private function revokeActiveConsents(int $personRelatedId, string $now): void
    {
        PersonDelegationConsent::updateAll(
            [
                'status' => DelegationConsentStatus::REVOKED,
                'revoked_at' => $now,
                'updated_at' => $now,
            ],
            [
                'person_related_id' => $personRelatedId,
                'status' => DelegationConsentStatus::ACTIVE,
            ]
        );
    }

    private function assertPersonaWithAccount(int $idPersona, string $message): void
    {
        if ($idPersona <= 0) {
            throw new \InvalidArgumentException('Sesión sin persona.');
        }
        $persona = Persona::findOne($idPersona);
        if ($persona === null || (int) $persona->id_user <= 0) {
            throw new \InvalidArgumentException($message);
        }
    }

    private function sessionUserId(): ?int
    {
        $id = Yii::$app->user->id ?? null;

        return $id !== null ? (int) $id : null;
    }
}
