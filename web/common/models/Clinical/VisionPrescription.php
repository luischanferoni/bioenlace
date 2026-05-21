<?php

namespace common\models\Clinical;

use common\components\Clinical\Enum\RequestStatus;
use yii\db\ActiveRecord;

class VisionPrescription extends ActiveRecord
{
    use ClinicalRecordTrait;

    public static function tableName(): string
    {
        return 'vision_prescription';
    }

    public function rules(): array
    {
        return [
            [['encounter_id', 'subject_persona_id', 'status'], 'required'],
            [['encounter_id', 'subject_persona_id'], 'integer'],
            [['status'], 'string', 'max' => 32],
            [['lens_spec_json', 'note'], 'string'],
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
