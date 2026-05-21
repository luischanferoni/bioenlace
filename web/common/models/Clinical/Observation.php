<?php

namespace common\models\Clinical;

use yii\db\ActiveRecord;

class Observation extends ActiveRecord
{
    use ClinicalRecordTrait;

    public const CATEGORY_EXAM = 'exam';
    public const CATEGORY_OPHTHALMOLOGY = 'ophthalmology';

    public static function tableName(): string
    {
        return 'observation';
    }

    public function rules(): array
    {
        return [
            [['encounter_id', 'subject_persona_id', 'status', 'category', 'code'], 'required'],
            [['encounter_id', 'subject_persona_id'], 'integer'],
            [['status', 'category'], 'string', 'max' => 64],
            [['code', 'code_system'], 'string', 'max' => 64],
            [['value_unit'], 'string', 'max' => 32],
            [['value_quantity'], 'number'],
            [['value_string', 'value_json', 'note'], 'string'],
            [['effective_datetime'], 'safe'],
        ];
    }

    public function getEncounter(): \yii\db\ActiveQuery
    {
        return $this->hasOne(Encounter::class, ['id' => 'encounter_id']);
    }
}
