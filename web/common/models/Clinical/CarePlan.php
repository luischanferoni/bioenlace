<?php

namespace common\models\Clinical;

use common\models\Persona;
use yii\db\ActiveRecord;

/**
 * @property int $id
 * @property int $subject_persona_id
 * @property string $status
 * @property string $intent
 * @property string $category
 * @property string|null $period_start
 * @property string|null $period_end
 * @property int|null $encounter_id
 * @property int|null $episode_of_care_id
 */
class CarePlan extends ActiveRecord
{
    use ClinicalRecordTrait;

    public static function tableName(): string
    {
        return 'care_plan';
    }

    public function rules(): array
    {
        return [
            [['subject_persona_id', 'status', 'intent', 'category'], 'required'],
            [['subject_persona_id', 'encounter_id', 'episode_of_care_id'], 'integer'],
            [['status'], 'string', 'max' => 32],
            [['intent'], 'string', 'max' => 16],
            [['category'], 'string', 'max' => 64],
            [['period_start', 'period_end', 'created_at', 'updated_at', 'deleted_at'], 'safe'],
            [['title'], 'string', 'max' => 255],
            [['description'], 'string'],
        ];
    }

    public function getSubject(): \yii\db\ActiveQuery
    {
        return $this->hasOne(Persona::class, ['id_persona' => 'subject_persona_id']);
    }

    public function getEncounter(): \yii\db\ActiveQuery
    {
        return $this->hasOne(Encounter::class, ['id' => 'encounter_id']);
    }

    public function getActivities(): \yii\db\ActiveQuery
    {
        return $this->hasMany(CarePlanActivity::class, ['care_plan_id' => 'id']);
    }
}
