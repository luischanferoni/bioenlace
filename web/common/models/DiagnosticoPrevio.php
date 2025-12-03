<?php

namespace common\models;

use Yii;
use common\models\snomed\SnomedHallazgos;
use common\models\DiagnosticoConsultaRepository as DiagnosticoRepo;

/**
 * This is the model class for table "diagnostico_consultas".
 *
 * @property string $id_consulta
 * @property string $codigo
 * @property string $tipo_diagnostico
 *
 * @property Cie10 $codigo0
 * @property Consultas $idConsulta
 */
class DiagnosticoPrevio extends DiagnosticoConsulta
{
    public $diagnostico;
    public $current_state;
    public $resolve;
    public $new_cclinical_status;
    public $new_cverification_status;
    
    
    public function attributeLabels()
    {
        return [
            'resolve' => 'Seguimiento',
            'new_cclinical_status' => 'Nuevo E. Clínico',
            'new_cverification_status' => 'Nuevo E. Verificación',
        ];
    }
    
    public function rules()
    {
        $rules = parent::rules();
        
        $rules[] = ['id', 'integer'];
        $rules[] = [[
            'diagnostico',
            'current_state',
            'resolve', 
            'new_cclinical_status', 
            'new_cverification_status'], 'string'];
        $rules[] = [['resolve'], 'default', 'value'=> 'N'];
        //$rules[] = [[
        //    'new_cclinical_status', 
        //    'new_cverification_status'], 'required', 
        //        'when' => function ($model) {
        //            Yii::debug("ALEX: validando en WHEN resolve:". $model->resolve);
        //            return $model->resolve == 'Y';
        //        }];
        
        return $rules;
    }
    
    public function afterFind()
    {
        $this->setCustomAttributes();
        parent::afterFind();
    }

    public function setCustomAttributes(){
        // Inicializar attibutos no almacenados en BD
        
        $clinical_label = 
            DiagnosticoRepo::getClinicalStatusDisplayLabel(
                    $this->condition_clinical_status);
        $verfication_label =
            DiagnosticoRepo::getVerificationStatusDisplayLabel(
                    $this->condition_verification_status);
        $this->current_state = "$clinical_label | $verfication_label";
        $this->diagnostico = $this->getDiagnosticoTerm();
        $this->resolve = 'N';
    }
    
    public function save($runValidation = true, $attributeNames = null) {
        throw Exception("Save restringido para esta clase");
    }
}
