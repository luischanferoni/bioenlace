<?php

namespace common\models\form;

use Yii;
use common\models\DiagnosticoConsulta;
use common\models\DiagnosticoConsultaRepository as DCRepo;


class IMPDiagnosticoForm extends ConsultaDiagnosticosForm
{
    /*
     *  retorna diagnosticos previos del paciente 
     */
    protected function getDiagnosticosPrevios() {
        return DCRepo::getDiagnosticosPreviosPendientesIMP($this->consulta);
    }
    
    /*
     * Valida form en forma "global"
     * 
     * returns bool
     */
    public function validate($attributeNames = null, $clearErrors = true) {
        parent::validate($attributeNames, $clearErrors);
        
        # Si hay diagnosticos previos, validarlos
        $this->validarDiagnosticosPrevios();
        
        # si no hay diagnosticos previos con resolucion,
        # en internación no es requerido un diagnóstico
        # No se valida otra cosa.
        
        return !$this->hasErrors();
    }
    
    /*
     * Minimos diagnosticos requeridos para consulta actual 
     */
    public function minimosDiagnosticosRequeridos(){
        # Internación no requiere diagnosticos en carga de consulta.
        return 0;
    }
}