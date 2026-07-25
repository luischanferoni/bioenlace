<?php

namespace common\models\Clinical;

use common\components\Domain\Clinical\Prescription\Enum\PrescriptionLegalStatus;
use yii\db\ActiveQuery;
use yii\db\ActiveRecord;

class ElectronicPrescription extends ActiveRecord
{
    use ClinicalRecordTrait;

    /** Emisión hacia RDI: integridad clínica (no knobs de política). */
    public const SCENARIO_RDI_ISSUE = 'rdi_issue';

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
            [
                ['diagnosis_code'],
                'trim',
                'on' => self::SCENARIO_RDI_ISSUE,
            ],
            [
                ['id_profesional_efector_servicio'],
                'required',
                'on' => self::SCENARIO_RDI_ISSUE,
                'message' => 'Falta el profesional prescriptor (PES) en la receta.',
            ],
            [
                ['diagnosis_code'],
                'required',
                'on' => self::SCENARIO_RDI_ISSUE,
                'message' => 'Falta el diagnóstico codificado (requerido para receta digital).',
            ],
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
