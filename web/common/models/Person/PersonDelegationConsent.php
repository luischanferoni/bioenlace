<?php

namespace common\models\Person;

use common\components\Domain\Person\Representation\Enum\DelegationConsentStatus;
use yii\db\ActiveRecord;

/**
 * Consentimiento de delegación (≈ FHIR Consent, régimen B).
 *
 * @property int $id
 * @property int $person_related_id
 * @property string $status
 * @property string $granted_at
 * @property string|null $revoked_at
 * @property string|null $provision_json
 * @property string $created_at
 * @property string $updated_at
 *
 * @property PersonRelated $personRelated
 */
class PersonDelegationConsent extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%person_delegation_consent}}';
    }

    public function rules(): array
    {
        return [
            [['person_related_id', 'granted_at'], 'required'],
            [['person_related_id'], 'integer'],
            [['granted_at', 'revoked_at', 'provision_json', 'created_at', 'updated_at'], 'safe'],
            [['status'], 'string', 'max' => 16],
            [['status'], 'in', 'range' => [DelegationConsentStatus::ACTIVE, DelegationConsentStatus::REVOKED]],
        ];
    }

    public function getPersonRelated(): \yii\db\ActiveQuery
    {
        return $this->hasOne(PersonRelated::class, ['id' => 'person_related_id']);
    }

    public static function findActiveForLink(int $personRelatedId): ?self
    {
        if ($personRelatedId <= 0) {
            return null;
        }

        return static::findOne([
            'person_related_id' => $personRelatedId,
            'status' => DelegationConsentStatus::ACTIVE,
        ]);
    }

    /**
     * @return list<string>
     */
    public function getProvisionPermissionsList(): array
    {
        if ($this->provision_json === null || trim((string) $this->provision_json) === '') {
            return [];
        }
        $decoded = json_decode((string) $this->provision_json, true);
        if (!is_array($decoded)) {
            return [];
        }
        if (isset($decoded['permissions']) && is_array($decoded['permissions'])) {
            return array_values(array_filter(array_map('strval', $decoded['permissions'])));
        }

        return array_values(array_filter(array_map('strval', $decoded)));
    }
}
