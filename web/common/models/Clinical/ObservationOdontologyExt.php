<?php

namespace common\models\Clinical;

use yii\db\ActiveRecord;

/**
 * Extensión odontológica 1:1 con {@see Observation} — pieza, caras y momento.
 */
class ObservationOdontologyExt extends ActiveRecord
{
    public static function tableName(): string
    {
        return 'observation_odontology_ext';
    }

    public function rules(): array
    {
        return [
            [['observation_id'], 'required'],
            [['observation_id'], 'integer'],
            [['tooth_number'], 'string', 'max' => 8],
            [['surfaces'], 'string', 'max' => 32],
            [['time_qualifier'], 'string', 'max' => 16],
        ];
    }

    public function getObservation(): \yii\db\ActiveQuery
    {
        return $this->hasOne(Observation::class, ['id' => 'observation_id']);
    }
}
