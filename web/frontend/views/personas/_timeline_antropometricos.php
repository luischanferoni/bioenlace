<?php
//var_dump($ultimaAtencionEnfermaria->datos);
$datos = json_decode($ultimaAtencionEnfermaria->datos, TRUE);
//echo $datos['inyectable'];
function mostrarDato($datos)
{
    if (is_array($datos)) {
        $valores = [];
        foreach ($datos as $key => $value) {
            switch ($key) {
                case 'sistolica':
                case '271649006':
                    $valores[0] = '<strong>Tensi&oacuten Arterial:</strong> ';
                    $valores[0] .= $value . '/';
                    break;
                case 'diastolica':
                case '271650006':
                    if (isset($valores[0])) {
                        $valores[0] .= $value;
                    } else {
                        $valores[0] = $value;
                    }
                    break;
                case 'TensionArterial1':
                    $valores[0] = '<strong>Tensi&oacuten Arterial #1:</strong> ';
                    $valores[0] .= $value[271649006] . '/' . $value[271650006] . '<br/>';
                    break;
                case 'TensionArterial2':
                    $valores[0] .= '<strong>Tensi&oacuten Arterial #2:</strong> ';
                    $valores[0] .= $value[271649006] . '/' . $value[271650006];
                    break;
                case 'peso':
                case '162879003p':
                    $valores[1] = '<strong>Peso/Talla:</strong> ';
                    $valores[1] .= 'P: ' . $value . 'kg. - ';
                    break;
                case 'talla':
                case '162879003t':
                    if (isset($valores[1])) {
                        $valores[1] .= 'T: ' . $value . 'cm.';
                    } else {
                        $valores[1] = 'T: ' . $value . 'cm.';
                    }
                    break;
                case 'agudeza_ojo_izquierdo':
                case '386708005':
                    $valores[2] = '<strong>Agudeza Visual:</strong> ';
                    $valores[2] .= 'OI: ' . $value . ' - ';
                    break;
                case 'agudeza_ojo_derecho':
                case '386709002':
                    $valores[2] .= 'OD: ' . $value;
                    break;
                case 'temperatura':
                case '703421000':
                    $valores[3] = '<strong>Temperatura:</strong> ';
                    $valores[3] .= $value . 'º';
                    break;
                case 'glucemia_capilar':
                case '434912009':
                    $valores[4] = '<strong>Glucemia Capilar:</strong> ';
                    $valores[4] .= $value;
                    break;
                case 'circunferencia_abdominal':
                case '396552003':
                    $valores[5] = '<strong>Circunferencia Abdominal:</strong> ';
                    $valores[5] .= $value . 'cm.';
                    break;
                case 'perimetro_cefalico':
                case '363812007':
                    $valores[6] = '<strong>Perimetro Cefálico:</strong> ';
                    $valores[6] .= $value . 'cm.';
                    break;
                case 'campaña':
                    $valores[7] = '<strong>Campaña:</strong> ';
                    $valores[7] .= 'SI';
                    break;

                case 'nebulizacion':
                    $valores[7] = '<strong>Nebulización:</strong> ';
                    $valores[7] .= 'SI';
                    break;
                case 'rescate_sbo':
                    $valores[8] = '<strong>Rescate y SBO:</strong> ';
                    $valores[8] .= 'SI';
                    break;
                case 'inyectable':
                    $valores[9] = '<strong>Inyectable:</strong> ';
                    $valores[9] .= 'SI';
                    break;
                case 'inmunizacion':
                    $valores[10] = '<strong>Inmunización:</strong> ';
                    $valores[10] .= 'SI';
                    break;
                case 'extraccion_puntos':
                    $valores[11] = '<strong>Extracción Puntos:</strong> ';
                    $valores[11] .= 'SI';
                    break;
                case 'curacion':
                    $valores[12] = '<strong>Curación:</strong> ';
                    $valores[12] .= 'SI';
                    break;
                case 'internacion_abreviada':
                    $valores[13] = '<strong>Internacion Abreviada:</strong> ';
                    $valores[13] .= 'SI';
                    break;
                case 'visita_domiciliaria':
                    $valores[14] = '<strong>Visita Domiciliaria:</strong> ';
                    $valores[14] .= 'SI';
                    break;
                case 'electrocardiograma':
                    $valores[15] = '<strong>Electrocardiograma:</strong> ';
                    $valores[15] .= 'SI';
                    break;
                default:
                    break;
            }
        }
        if (count($valores) == 0) {
            $valores[0] = 'Sin datos: ';
            $valores[0] .= '/';
        }
        return $valores;
    }
}

?>

<div class="row pt-5">
    <div class="col-12">
        <span class="text-danger">Presión Sanguínea</span>
    </div>
    <div class="col-6">
        <h2 class="float-start mt-0 me-2"><strong><?=isset($datos['sistolica'])?$datos['sistolica']:'--'?></strong></h2>
        <div>
            <p class="mb-0"><small>Sistolica</small></p> 
            <p class="mb-0 mt-0"><small class="vertical-align-super">mmHg</small></p>
        </div>
    </div>							
    <div class="col-6 bl-1">
        <h2 class="float-start mt-0 me-2"><strong><?=isset($datos['diastolica'])?$datos['diastolica']:'--'?></strong></h2>
        <div>
            <p class="mb-0"><small>Diastolica</small></p> 
            <p class="mb-0 mt-0"><small class="vertical-align-super">mmHg</small></p>
        </div>
    </div>
</div>

<div class="d-flex align-items-center justify-content-between mt-3 mb-4 flex-wrap">							
    <div class="d-flex align-items-center">							  
        <i class="bi bi-universal-access"></i>
        <div class="ms-3">
            <p class="mb-0"><small>Peso</small></p> 
            <h5 class="mb-0"><strong><?=isset($datos['peso'])?$datos['peso']:'--'?></strong></h5>
        </div>
    </div>							
    <div class="d-flex align-items-center">							  
        <i class="bi bi-universal-access"></i>
        <div class="ms-3">
            <p class="mb-0"><small>Altura</small></p> 
            <h5 class=" mb-0"><strong><?=isset($datos['altura'])?$datos['altura']:'--'?></strong></h5>
        </div>
    </div>							
    <div class="d-flex align-items-center">							  
        <i class="bi bi-activity"></i>
        <div class="ms-3">
            <p class="mb-0"><small>IMC</small></p> 
            <h5 class="mb-0"><strong>30.34</strong></h5>
        </div>
    </div>
</div>

<div class="d-flex align-items-center justify-content-between mt-3 mb-4 flex-wrap">							
    <div class="d-flex align-items-center">							  
        <i class="bi bi-universal-access"></i>
        <div class="ms-1">
            <p class="mb-0"><small>Temperatura</small></p> 
            <h5 class="mb-0"><strong><?=isset($datos['temperatura'])?$datos['temperatura']:'--'?></strong></h5>
        </div>
    </div>							
    <div class="d-flex align-items-center">							  
        <i class="bi bi-universal-access"></i>
        <div class="ms-1">
            <p class="mb-0"><small>Glucemia Capilar</small></p> 
            <h5 class=" mb-0"><strong><?=isset($datos['glucemia_capilar'])?$datos['glucemia_capilar']:'--'?></strong></h5>
        </div>
    </div>							
    <div class="d-flex align-items-center">							  
        <i class="bi bi-activity"></i>
        <div class="ms-1">
            <p class="mb-0"><small>Circunferencia Abdominal</small></p> 
            <h5 class="mb-0"><strong>30.34</strong></h5>
        </div>
    </div>
</div>

<div class="row pt-5">
    <div class="col-12">
        <span class="text-danger">Agudeza Visual</span>
    </div>
    <div class="col-6">
        <h2 class="float-start mt-0 me-2"><strong><?=isset($datos['av_ojo_izquierdo'])?$datos['av_ojo_izquierdo']:'--'?></strong></h2>
        <div>
            <p class="mb-0"><small>Ojo Izquierdo</small></p>
        </div>
    </div>							
    <div class="col-6 bl-1">
        <h2 class="float-start mt-0 me-2"><strong><?=isset($datos['av_ojo_derecho'])?$datos['av_ojo_derecho']:'--'?></strong></h2>
        <div>
            <p class="mb-0"><small>Ojo Derecho</small></p>
        </div>
    </div>
</div>