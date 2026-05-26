<?php

namespace common\models\Clinical;

use yii\db\ActiveRecord;

/**
 * Indicaciones de régimen / dieta (ex {@see \common\models\ConsultaRegimen}).
 */
class NutritionOrder extends ActiveRecord
{
    use ClinicalRecordTrait;

    public const STATUS_ACTIVE = 'active';

    public static function tableName(): string
    {
        return 'nutrition_order';
    }

    public function rules(): array
    {
        return [
            [['encounter_id', 'subject_persona_id', 'status'], 'required'],
            [['encounter_id', 'subject_persona_id'], 'integer'],
            [['status'], 'string', 'max' => 32],
            [['oral_diet_json', 'note'], 'string'],
        ];
    }

    public function getEncounter(): \yii\db\ActiveQuery
    {
        return $this->hasOne(Encounter::class, ['id' => 'encounter_id']);
    }
}
