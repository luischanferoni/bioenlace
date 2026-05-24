<?php

namespace common\models\Clinical;

use common\components\Clinical\Prescription\Enum\PrescriptionLegalStatus;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

class ElectronicPrescription extends ActiveRecord
{
    use ClinicalRecordTrait;

    public static function tableName(): string
    {
        return 'electronic_prescription';
    }

    public function rules(): array
    {
        return [
            [['encounter_id', 'subject_persona_id', 'status'], 'required'],
            [['encounter_id', 'subject_persona_id', 'id_profesional_efector_servicio'], 'integer'],
            [['status'], 'in', 'range' => PrescriptionLegalStatus::all()],
            [['prescription_number'], 'string', 'max' => 64],
            [['diagnosis_code'], 'string', 'max' => 64],
            [['diagnosis_code_system'], 'string', 'max' => 128],
            [['diagnosis_display'], 'string', 'max' => 512],
            [['valid_from', 'valid_until', 'issued_at', 'cancelled_at'], 'safe'],
            [['cancellation_reason', 'fhir_bundle_json', 'notes'], 'string'],
            [['verification_token'], 'string', 'max' => 64],
            [['document_hash'], 'string', 'max' => 64],
            [['signature_provider'], 'string', 'max' => 64],
            [['signed_at'], 'safe'],
        ];
    }

    /** @return ActiveQuery<ElectronicPrescriptionItem> */
    public function getItems(): ActiveQuery
    {
        return $this->hasMany(ElectronicPrescriptionItem::class, ['electronic_prescription_id' => 'id'])
            ->andWhere(['deleted_at' => null])
            ->orderBy(['line_number' => SORT_ASC]);
    }

    public function getEncounter(): ActiveQuery
    {
        return $this->hasOne(Encounter::class, ['id' => 'encounter_id']);
    }
}
