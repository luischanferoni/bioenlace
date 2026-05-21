<?php

namespace common\models\Clinical;

use common\models\Persona;
use yii\db\ActiveRecord;

class EpisodeOfCare extends ActiveRecord
{
    use ClinicalRecordTrait;

    public static function tableName(): string
    {
        return 'episode_of_care';
    }

    public function rules(): array
    {
        return [
            [['subject_persona_id', 'status', 'type_code'], 'required'],
            [['subject_persona_id', 'efector_id', 'internacion_id'], 'integer'],
            [['status'], 'string', 'max' => 32],
            [['type_code'], 'string', 'max' => 64],
            [['period_start', 'period_end'], 'safe'],
            [['title'], 'string', 'max' => 255],
            [['description'], 'string'],
        ];
    }

    public function getSubject(): \yii\db\ActiveQuery
    {
        return $this->hasOne(Persona::class, ['id_persona' => 'subject_persona_id']);
    }
}
