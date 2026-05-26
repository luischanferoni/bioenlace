<?php

namespace common\models\Clinical;

use common\models\Persona;
use yii\db\ActiveRecord;

/**
 * FHIR AllergyIntolerance — tabla `allergy_intolerance`.
 */
class AllergyIntolerance extends ActiveRecord
{
    use ClinicalRecordTrait;

    public const TYPE_ALLERGY = 'allergy';
    public const TYPE_INTOLERANCE = 'intolerance';

    public const CATEGORY_FOOD = 'food';
    public const CATEGORY_MEDICATION = 'medication';
    public const CATEGORY_ENVIRONMENT = 'environment';
    public const CATEGORY_BIOLOGY = 'biology';

    public const CRITICALITY_LOW = 'low';
    public const CRITICALITY_HIGH = 'high';
    public const CRITICALITY_UNABLE_TO_ASSESS = 'unable-to-assess';

    public static function tableName(): string
    {
        return 'allergy_intolerance';
    }

    public function rules(): array
    {
        return [
            [['subject_persona_id', 'clinical_status', 'verification_status'], 'required'],
            [['encounter_id', 'subject_persona_id'], 'integer'],
            [['type', 'category', 'clinical_status', 'verification_status', 'criticality'], 'string', 'max' => 32],
            [['code'], 'string', 'max' => 64],
            [['display'], 'string', 'max' => 512],
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
}
