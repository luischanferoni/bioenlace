<?php

namespace common\models\Clinical;

use yii\db\ActiveRecord;

class ElectronicPrescriptionEvent extends ActiveRecord
{
    public static function tableName(): string
    {
        return 'electronic_prescription_event';
    }

    public function rules(): array
    {
        return [
            [['electronic_prescription_id', 'event_type'], 'required'],
            [['electronic_prescription_id', 'actor_user_id'], 'integer'],
            [['event_type'], 'string', 'max' => 32],
            [['payload_json'], 'string'],
            [['created_at'], 'safe'],
        ];
    }
}
