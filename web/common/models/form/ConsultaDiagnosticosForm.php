<?php

namespace common\models\form;

use Yii;
use yii\helpers\ArrayHelper;
use common\models\FormularioDinamico;
use common\models\snomed\SnomedHallazgos;
use common\models\DiagnosticoConsulta;
use common\models\DiagnosticoPrevio;
use common\models\DiagnosticoConsultaRepository as DCRepo;


class ConsultaDiagnosticosForm extends \yii\base\Model
{
    public $consulta;
    public $diagnosticos;
    public $diag_previos;
    
    protected $idsBDPrevioPost;
    
    /*
     *  retorna diagnosticos de la consulta,
     *  No relacionados a diagnosticos previos
     */
    protected function getDiagnosticos() {
        return DCRepo::getDiagnosticos($this->consulta);
    }
    
    /*
     *  retorna diagnosticos previos del paciente 
     */
    protected function getDiagnosticosPrevios() {
        throw new \Exception("Implement in derived class");
    }
    
    public function prepareForm($consulta) {
        $this->consulta = $consulta;
        $this->diagnosticos = $this->getDiagnosticos();
        $this->diag_previos = $this->getDiagnosticosPrevios();
    }
    
    public function loadFomPost() {
        $this->idsBDPrevioPost = ArrayHelper::getColumn(
                $this->diagnosticos, 'id');
            
        // cargar modificaciones en diagnosticos previos:
        $this->diag_previos = FormularioDinamico::createAndLoadMultiple(
            DiagnosticoPrevio::classname(), 'id', $this->diag_previos);

        $this->diagnosticos = FormularioDinamico::createAndLoadMultiple(
                DiagnosticoConsulta::classname(),
                'id', 
                $this->diagnosticos);
        // set consulta id on children
        foreach ($this->diagnosticos as $i => $diagnostico) {
            $diagnostico->id_consulta = $this->consulta->id_consulta;
            Yii::debug("FRM diagnostico loaded: ".$diagnostico->id);
        }
    }
    
    public function validatePost() {
        // VAlidate before save
        $valid = $this->consulta->isNewRecord?
                $this->consulta->save():
                $this->consulta->validate();
        $valid = FormularioDinamico::validateMultiple($this->diagnosticos)
                && $valid;
        $form_model_valid = $this->validate();
        Yii::debug("FORM model valid:".$form_model_valid);
        $valid = $form_model_valid && $valid;
        return $valid;
    }
    
    public function processPost() {
        // Procesar diagnosticos previos
        DCRepo::saveDiagnosticosPrevios($this->consulta, $this->diag_previos);

        // Procesar nuevos diagnosticos
        $idsEnPost = [];
        foreach ($this->diagnosticos as $i => $diagnostico) {
            if (!$diagnostico->save()) {
                $msg = 'Error al guardar ConsultaDiagnostico: '.$i;
                throw new \Exception($msg);
            }
            SnomedHallazgos::crearSiNoExiste(
                    $diagnostico->codigo, 
                    Yii::$app->request->post(
                            "CustomAttribute")[$i]["termino_hallazgo"]
                    );
            $idsEnPost[] = $diagnostico->id;
        }
        // eliminar los que estaban en la BD y no vienen en el post
        $idsAEliminar = array_diff($this->idsBDPrevioPost, $idsEnPost);
        // hard delete, hardDeleteGrupo verifica que $idsAEliminar no sea vacio
        DiagnosticoConsulta::hardDeleteGrupo(
                $this->consulta->id_consulta,
                $idsAEliminar);
    }

    public function rules()
    {
        return [
        ];
    }
    
    protected function hasDiagPreviosConResolucion() {
        $has = False;
        foreach($this->diag_previos as $d) {
            if($d->resolve == 'Y') {
                $has = true;
                break;
            }
        }
        return $has;
    }
    
    protected function hasDiagPrevios() {
        if(is_countable($this->diag_previos)) {
            return count($this->diag_previos) > 0;
        }
        return false;
    }
    
    protected function hasNuevosDiagnosticos() {
        $count = 0;
        if(is_countable($this->diagnosticos)) {
            $count = count($this->diagnosticos);
        }
        Yii::debug("Cantidad de nuevos: ".$count);
        return  $count > 0;
    }
    
    public function minimosDiagnosticosRequeridos(){
        // Minimos nuevos diagnosticos requeridos
        
        if(is_countable($this->diag_previos) && count($this->diag_previos) > 0)
            return 0;
        return 1;
    }
    
    
    public function hasDiagnosticosNuevosSinGuardar() {
        $count = 0;
        if(is_iterable($this->diagnosticos)) {
            foreach ($this->diagnosticos as $d) {
                if($d->isNewRecord) {
                    $count += 1;
                }
            }
        }
        return $count > 0;
    }
    
    public function validarDiagnosticosPrevios() {
        $this->validarEstadosRequeridos();
        $this->validarResolveOlvidado();
    }
    
    /*
     * Validar estados requeridos si el usuario tildo resolver
     */
    protected function validarEstadosRequeridos() {
        $has_errors = False;
        foreach($this->diag_previos as $d) {
            if($d->resolve == 'N')
                continue;
            // Si es cronico no validar nuevos estados
            if($d->cronico == 'SI')
                continue;
            // resolve is Y so validate statuses:
            if(empty($d->new_cclinical_status)) {
                $d->addError('new_cclinical_status', 'Es requerido.');
                $has_errors = True;
            }
            if(empty($d->new_cverification_status)) {
                $d->addError('new_cverification_status', 'Es requerido.');
                $has_errors = True;
            }
        }
        if($has_errors) {
            $this->addError('*', 'Debe completar los campos requeridos.');
        }
    }
    
    /*
     * Validar resolve olvidado. El usuario modifico los estados
     * pero no tildo resolver. Avisarle.
     */
    protected function validarResolveOlvidado() {
        $has_errors = False;
        foreach($this->diag_previos as $d) {
            if($d->resolve == 'N') {
                // resolve is Y so validate statuses:
                if(!empty($d->new_cclinical_status)
                   || !empty($d->new_cverification_status)) {
                    $has_errors = True;
                    $d->addError('resolve', '¿Olvidó tildar?');
                }
            }
        }
        if($has_errors) {
            $msg = 'Debe indicar seguimiento si modificó los estados '
                . 'de diagnósticos previos.';
            $this->addError('*', $msg);
        }
    }
    
    public function getDiagnosticoTemplate() {
        // El proposito es inicializar la linea de form
        // al agregar un diagnostico, pero no esta
        // funcionando como se espera.
        $diag = new DiagnosticoConsulta();
        $diag->condition_clinical_status = 
            DiagnosticoConsulta::CLINICAL_STATUS_ACTIVE;
        $diag->condition_verification_status =
            DiagnosticoConsulta::VERIFICATION_STATUS_PROVISIONAL;
        $diag->cronico = 'NO';
        return $diag;
    }
}