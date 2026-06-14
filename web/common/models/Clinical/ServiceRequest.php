<?php

namespace common\models\Clinical;

use common\components\Domain\Clinical\Enum\RequestStatus;
use yii\db\ActiveRecord;

class ServiceRequest extends ActiveRecord
{
    use ClinicalRecordTrait;

    public static function tableName(): string
    {
        return 'service_request';
    }

    public function rules(): array
    {
        return [
            [['encounter_id', 'subject_persona_id', 'status', 'intent', 'category'], 'required'],
            [['encounter_id', 'subject_persona_id', 'care_plan_id', 'id_profesional_efector_servicio'], 'integer'],
            [['target_efector_id', 'target_service_id', 'responded_encounter_id'], 'integer'],
            [['status'], 'string', 'max' => 32],
            [['intent'], 'string', 'max' => 16],
            [['category'], 'string', 'max' => 64],
            [['referral_status', 'referral_kind', 'request_kind'], 'string', 'max' => 32],
            [['code'], 'string', 'max' => 64],
            [['code_system'], 'string', 'max' => 64],
            [['display'], 'string', 'max' => 512],
            [['occurrence_datetime'], 'safe'],
            [['note', 'reminder_json'], 'string'],
        ];
    }

    public function getEncounter(): \yii\db\ActiveQuery
    {
        return $this->hasOne(Encounter::class, ['id' => 'encounter_id']);
    }

    public function beforeValidate(): bool
    {
        if ($this->isNewRecord && empty($this->status)) {
            $this->status = RequestStatus::ACTIVE;
        }

        return parent::beforeValidate();
    }
}
