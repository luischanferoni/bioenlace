<?php

namespace common\models\Clinical;

use common\models\Persona;
use yii\db\ActiveRecord;

/**
 * FHIR Condition — tabla `clinical_condition`.
 */
class Condition extends ActiveRecord
{
    use ClinicalRecordTrait;

    public static function tableName(): string
    {
        return 'clinical_condition';
    }

    public function rules(): array
    {
        return [
            [['encounter_id', 'subject_persona_id', 'code', 'clinical_status', 'verification_status'], 'required'],
            [['encounter_id', 'subject_persona_id'], 'integer'],
            [['code'], 'string', 'max' => 32],
            [['code_system'], 'string', 'max' => 64],
            [['display'], 'string', 'max' => 512],
            [['clinical_status', 'verification_status', 'diagnosis_role'], 'string', 'max' => 32],
            [['recorded_date', 'onset_datetime'], 'safe'],
            [['note'], 'string'],
        ];
    }

    public function getEncounter(): \yii\db\ActiveQuery
    {
        return $this->hasOne(Encounter::class, ['id' => 'encounter_id']);
    }

    public function getSubject(): \yii\db\ActiveQuery
    {
        return $this->hasOne(Persona::class, ['id_persona' => 'subject_persona_id']);
    }

    public static function clinicalStatusFromLegacy(string $legacy): string
    {
        return $legacy;
    }

    public static function verificationStatusFromLegacy(string $legacy): string
    {
        return $legacy;
    }
}
