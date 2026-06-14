<?php

namespace common\models\Person;

use common\components\Domain\Person\Representation\Enum\PersonRelatedStatus;
use common\components\Domain\Person\Representation\Enum\RepresentationRegime;
use yii\db\ActiveRecord;

/**
 * Vínculo operativo actor ↔ sujeto (≈ FHIR RelatedPerson).
 *
 * @property int $id
 * @property int $subject_persona_id
 * @property int $actor_persona_id
 * @property int $relationship_type_id
 * @property string $regime
 * @property string $status
 * @property string $verified_by
 * @property string|null $verified_at
 * @property string|null $blocked_reason
 * @property string|null $blocked_at
 * @property int|null $blocked_by_user_id
 * @property string|null $permissions_json
 * @property string|null $evidence_json
 * @property string $created_at
 * @property string $updated_at
 *
 * @property RelationshipType $relationshipType
 * @property PersonDelegationConsent|null $activeConsent
 */
class PersonRelated extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%person_related}}';
    }

    public function rules(): array
    {
        return [
            [['subject_persona_id', 'actor_persona_id', 'relationship_type_id', 'regime'], 'required'],
            [['subject_persona_id', 'actor_persona_id', 'relationship_type_id', 'blocked_by_user_id'], 'integer'],
            [['verified_at', 'blocked_at', 'permissions_json', 'evidence_json', 'created_at', 'updated_at'], 'safe'],
            [['regime'], 'string', 'max' => 32],
            [['status', 'verified_by'], 'string', 'max' => 16],
            [['blocked_reason'], 'string', 'max' => 32],
            [['regime'], 'in', 'range' => RepresentationRegime::all()],
            [['status'], 'in', 'range' => [
                PersonRelatedStatus::PENDING,
                PersonRelatedStatus::ACTIVE,
                PersonRelatedStatus::REVOKED,
                PersonRelatedStatus::BLOCKED,
            ]],
        ];
    }

    public function getRelationshipType(): \yii\db\ActiveQuery
    {
        return $this->hasOne(RelationshipType::class, ['id' => 'relationship_type_id']);
    }

    public function getActiveConsent(): \yii\db\ActiveQuery
    {
        return $this->hasOne(PersonDelegationConsent::class, ['person_related_id' => 'id'])
            ->andWhere(['status' => \common\components\Domain\Person\Representation\Enum\DelegationConsentStatus::ACTIVE]);
    }

    public static function findActiveLink(int $actorPersonaId, int $subjectPersonaId): ?self
    {
        if ($actorPersonaId <= 0 || $subjectPersonaId <= 0) {
            return null;
        }

        return static::findOne([
            'actor_persona_id' => $actorPersonaId,
            'subject_persona_id' => $subjectPersonaId,
            'status' => PersonRelatedStatus::ACTIVE,
        ]);
    }

    /**
     * @return list<string>
     */
    public function getPermissionsList(): array
    {
        if ($this->permissions_json === null || trim((string) $this->permissions_json) === '') {
            return [];
        }
        $decoded = json_decode((string) $this->permissions_json, true);
        if (!is_array($decoded)) {
            return [];
        }
        if (isset($decoded['permissions']) && is_array($decoded['permissions'])) {
            return array_values(array_filter(array_map('strval', $decoded['permissions'])));
        }

        return array_values(array_filter(array_map('strval', $decoded)));
    }
}
