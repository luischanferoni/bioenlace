<?php

namespace common\models\Clinical;

use common\components\Domain\Clinical\Enum\ProcedureStatus;
use yii\db\ActiveRecord;

class Procedure extends ActiveRecord
{
    use ClinicalRecordTrait;

    public static function tableName(): string
    {
        return 'procedure';
    }

    public function rules(): array
    {
        return [
            [['encounter_id', 'subject_persona_id', 'status'], 'required'],
            [['encounter_id', 'subject_persona_id', 'service_request_id'], 'integer'],
            [['status'], 'string', 'max' => 32],
            [['code', 'code_system'], 'string', 'max' => 64],
            [['display'], 'string', 'max' => 512],
            [['performed_datetime'], 'safe'],
            [['note'], 'string'],
        ];
    }

    public function getEncounter(): \yii\db\ActiveQuery
    {
        return $this->hasOne(Encounter::class, ['id' => 'encounter_id']);
    }

    public function getOdontologyExt(): \yii\db\ActiveQuery
    {
        return $this->hasOne(ProcedureOdontologyExt::class, ['procedure_id' => 'id']);
    }

    public function beforeValidate(): bool
    {
        if ($this->isNewRecord && $this->status === '') {
            $this->status = ProcedureStatus::COMPLETED;
        }

        return parent::beforeValidate();
    }
}
