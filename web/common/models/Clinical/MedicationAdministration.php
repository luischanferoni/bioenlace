<?php

namespace common\models\Clinical;

use yii\db\ActiveRecord;

/**
 * Administración / suministro de medicación (ex {@see \common\models\ConsultaSuministroMedicamento}).
 */
class MedicationAdministration extends ActiveRecord
{
    use ClinicalRecordTrait;

    public const STATUS_COMPLETED = 'completed';

    public static function tableName(): string
    {
        return 'medication_administration';
    }

    public function rules(): array
    {
        return [
            [['encounter_id', 'status'], 'required'],
            [['encounter_id', 'medication_request_id'], 'integer'],
            [['status'], 'string', 'max' => 32],
            [['effective_datetime'], 'safe'],
            [['dosage_json'], 'string'],
        ];
    }

    public function getEncounter(): \yii\db\ActiveQuery
    {
        return $this->hasOne(Encounter::class, ['id' => 'encounter_id']);
    }

    public function getMedicationRequest(): \yii\db\ActiveQuery
    {
        return $this->hasOne(MedicationRequest::class, ['id' => 'medication_request_id']);
    }
}
