<?php

namespace common\models\Clinical;

use common\models\Person\Persona;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

class DiagnosticReport extends ActiveRecord
{
    use ClinicalRecordTrait;

    public static function tableName(): string
    {
        return 'diagnostic_report';
    }

    public function rules(): array
    {
        return [
            [['subject_persona_id', 'source_system', 'external_id', 'status'], 'required'],
            [['subject_persona_id', 'encounter_id'], 'integer'],
            [['issued_at'], 'safe'],
            [['source_system'], 'string', 'max' => 64],
            [['external_id', 'code'], 'string', 'max' => 128],
            [['status'], 'string', 'max' => 32],
            [['code_system'], 'string', 'max' => 128],
            [['display'], 'string', 'max' => 512],
            [['conclusion', 'payload_json'], 'string'],
            [['source_system', 'external_id'], 'unique', 'targetAttribute' => ['source_system', 'external_id']],
        ];
    }

    public function getEncounter(): ActiveQuery
    {
        return $this->hasOne(Encounter::class, ['id' => 'encounter_id']);
    }

    public function getSubjectPersona(): ActiveQuery
    {
        return $this->hasOne(Persona::class, ['id_persona' => 'subject_persona_id']);
    }

    public function getObservations(): ActiveQuery
    {
        return $this->hasMany(Observation::class, ['diagnostic_report_id' => 'id']);
    }
}
