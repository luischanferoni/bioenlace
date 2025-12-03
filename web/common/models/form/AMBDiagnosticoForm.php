<?php

namespace common\models\form;

use Yii;
use common\models\DiagnosticoConsulta;
use common\models\DiagnosticoConsultaRepository as DCRepo;


class AMBDiagnosticoForm extends ConsultaDiagnosticosForm
{
    /*
     *  retorna diagnosticos previos del paciente 
     */
    protected function getDiagnosticosPrevios() {
        return DCRepo::getDiagnosticosPreviosPendientes(
                    $this->consulta->id_persona
            );
    }
    
    public function validate($attributeNames = null, $clearErrors = true) {
        parent::validate($attributeNames, $clearErrors);
        $this->validarDiagnosticosPrevios();
        
        if(($this->hasDiagPrevios() && !$this->hasDiagPreviosConResolucion())
            && !$this->hasNuevosDiagnosticos()) {
            // Usuario no completo un diagnostico previo
            // y no agregó un nuevo diagnostico.
            $msg = "Debe completar el seguimiento de un ";
            $msg .= "diagnóstico existente o agregar un nuevo diagnóstico.";
            $this->addError('*', $msg);
        }
        
        return !$this->hasErrors();
    }
    
    public function minimosDiagnosticosRequeridos(){
        // Minimos nuevos diagnosticos requeridos
        
        if(is_countable($this->diag_previos) && count($this->diag_previos) > 0)
            return 0;
        return 1;
    }
}