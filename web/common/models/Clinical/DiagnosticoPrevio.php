<?php

namespace common\models\Clinical;

use yii\db\ActiveRecord;
use common\models\Terminology\Application\SnomedHallazgos;
use common\models\Clinical\EncounterDiagnosisRepository as DiagnosticoRepo;
use common\components\Domain\Clinical\Encounter\Domain\ConditionClinicalStatus;
use common\components\Domain\Clinical\Encounter\Domain\ConditionVerificationStatus;

/**
 * Lectura de diagnósticos previos vía {@see view_encounter_diagnostico}.
 *
 * @property int $id
 * @property int|null $encounter_id
 * @property string|null $codigo
 * @property string|null $tipo_diagnostico
 * @property string|null $cronico
 * @property string|null $condition_clinical_status
 * @property string|null $condition_verification_status
 *
 * @property-read SnomedHallazgos|null $codigoSnomed
 * @property-read Encounter|null $encounter
 */
class DiagnosticoPrevio extends ActiveRecord
{
    public $diagnostico;
    public $current_state;
    public $resolve;
    public $new_cclinical_status;
    public $new_cverification_status;
    public $terminos_motivos;
    public $id_servicio;

    /**
     * Lectura de diagnósticos actuales desde la vista de diagnósticos de encounter.
     */
    public static function tableName()
    {
        return 'view_encounter_diagnostico';
    }

    /**
     * La vista no expone su clave primaria en la caché de esquema de Yii.
     *
     * @return list<string>
     */
    public static function primaryKey()
    {
        return ['id'];
    }

    public function attributeLabels()
    {
        return [
            'encounter_id' => 'Encounter',
            'codigo' => 'Concepto',
            'tipo_diagnostico' => 'Tipo de Diagnóstico',
            'cronico' => 'Crónico',
            'condition_verification_status' => 'Estado de Verificación',
            'condition_clinical_status' => 'Estado Clínico',
            'resolve' => 'Seguimiento',
            'new_cclinical_status' => 'Nuevo E. Clínico',
            'new_cverification_status' => 'Nuevo E. Verificación',
        ];
    }

    public function rules()
    {
        return [
            [['encounter_id', 'codigo', 'condition_clinical_status', 'condition_verification_status'], 'required'],
            [['id', 'encounter_id', 'id_servicio'], 'integer'],
            [['condition_clinical_status'], 'default', 'value' => ConditionClinicalStatus::ACTIVE],
            [['condition_verification_status'], 'default', 'value' => ConditionVerificationStatus::PROVISIONAL],
            [['tipo_diagnostico', 'cronico', 'condition_verification_status', 'condition_clinical_status', 'terminos_motivos'], 'string'],
            [['codigo'], 'string', 'max' => 25],
            [['cronico'], 'default', 'value' => 'NO'],
            [[
                'diagnostico',
                'current_state',
                'resolve',
                'new_cclinical_status',
                'new_cverification_status',
            ], 'string'],
            [['resolve'], 'default', 'value' => 'N'],
        ];
    }

    public function afterFind()
    {
        $this->setCustomAttributes();
        parent::afterFind();
    }

    public function setCustomAttributes()
    {
        $clinical_label = DiagnosticoRepo::getClinicalStatusDisplayLabel(
            $this->condition_clinical_status
        );
        $verfication_label = DiagnosticoRepo::getVerificationStatusDisplayLabel(
            $this->condition_verification_status
        );
        $this->current_state = "$clinical_label | $verfication_label";
        $this->diagnostico = $this->getDiagnosticoTerm();
        $this->resolve = 'N';
    }

    public function getCodigoSnomed()
    {
        return $this->hasOne(SnomedHallazgos::class, ['conceptId' => 'codigo']);
    }

    public function getEncounter()
    {
        return $this->hasOne(Encounter::class, ['id' => 'encounter_id']);
    }

    public function getDiagnosticoTerm(): string
    {
        $sm = $this->codigoSnomed;
        if ($sm) {
            return (string) $sm->term;
        }

        return '?';
    }

    public function getCondVerificationStatusDesc(): string
    {
        return DiagnosticoRepo::getVerificationStatusDisplayLabel(
            $this->condition_verification_status
        );
    }

    public function getCondClinicalStatusDesc(): string
    {
        return DiagnosticoRepo::getClinicalStatusDisplayLabel(
            $this->condition_clinical_status
        );
    }

    public function getStatusesDesc(string $separator = '/'): string
    {
        return sprintf(
            '%s %s %s',
            DiagnosticoRepo::getClinicalStatusDisplayLabel($this->condition_clinical_status),
            $separator,
            DiagnosticoRepo::getVerificationStatusDisplayLabel($this->condition_verification_status)
        );
    }

    public function isCronico(): bool
    {
        return $this->cronico == 'SI';
    }

    public function save($runValidation = true, $attributeNames = null)
    {
        throw new \Exception('Save restringido para esta clase');
    }
}
