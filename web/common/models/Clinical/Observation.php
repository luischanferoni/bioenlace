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
            [['subject_persona_id', 'status', 'category', 'code'], 'required'],
            [['encounter_id', 'subject_persona_id', 'diagnostic_report_id'], 'integer'],
            [['status', 'category'], 'string', 'max' => 64],
            [['code'], 'string', 'max' => 128],
            [['code_system', 'source_system'], 'string', 'max' => 128],
            [['external_id'], 'string', 'max' => 128],
            [['value_unit'], 'string', 'max' => 32],
            [['value_quantity'], 'number'],
            [['value_string', 'value_json'], 'string'],
            [['effective_datetime'], 'safe'],
            [['source_system', 'external_id'], 'unique', 'targetAttribute' => ['source_system', 'external_id'], 'skipOnEmpty' => true],
        ];
    }

    public function getEncounter(): \yii\db\ActiveQuery
    {
        return $this->hasOne(Encounter::class, ['id' => 'encounter_id']);
    }

    public function getDiagnosticReport(): \yii\db\ActiveQuery
    {
        return $this->hasOne(DiagnosticReport::class, ['id' => 'diagnostic_report_id']);
    }
}
