<?php

namespace common\models\Clinical;

use yii\db\ActiveRecord;

class ElectronicPrescriptionItem extends ActiveRecord
{
    use ClinicalRecordTrait;

    public static function tableName(): string
    {
        return 'electronic_prescription_item';
    }

    public function rules(): array
    {
        return [
            [['electronic_prescription_id', 'line_number'], 'required'],
            [['electronic_prescription_id', 'medication_request_id', 'line_number'], 'integer'],
            [['medication_code'], 'string', 'max' => 64],
            [['medication_code_system'], 'string', 'max' => 128],
            [['medication_display'], 'string', 'max' => 512],
            [['quantity_text'], 'string', 'max' => 128],
            [['dosage_text'], 'string'],
        ];
    }
}
