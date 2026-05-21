<?php

namespace common\models\Clinical;

use common\components\Clinical\Enum\RequestStatus;
use yii\db\ActiveRecord;

class MedicationRequest extends ActiveRecord
{
    use ClinicalRecordTrait;

    public static function tableName(): string
    {
        return 'medication_request';
    }

    public function rules(): array
    {
        return [
            [['encounter_id', 'subject_persona_id', 'status', 'intent'], 'required'],
            [['encounter_id', 'subject_persona_id', 'care_plan_id', 'id_profesional_efector_servicio'], 'integer'],
            [['status'], 'string', 'max' => 32],
            [['intent'], 'string', 'max' => 16],
            [['medication_code'], 'string', 'max' => 64],
            [['medication_display'], 'string', 'max' => 512],
            [['dosage_text', 'dosage_json'], 'string'],
            [['authored_on'], 'safe'],
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
