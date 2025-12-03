<?php
use yii\helpers\ArrayHelper;
use common\models\Persona;
use common\models\Consulta;
use frontend\controllers\MpiApiController;

?>


<div class="row">
    <table>
        <tr>
            <td colspan="2">
                <p style="font-weight: bold">Ministerio de Salud y Desarrollo Social</p>
                <p style="font-weight: bold">Secretaria Técnica de Estadística de Salud </p>
            </td>
            <td colspan="1">
                <p style="text-indent: 0pt;text-align: left;"><span>
                    <table border="0" cellspacing="0" cellpadding="0"><tr><td><img style="margin-left: 20px; margin-right: 20px;" width="54" height="71" src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAADYAAABHCAYAAABMO7S5AAAABmJLR0QA/wD/AP+gvaeTAAAACXBIWXMAAA7EAAAOxAGVKw4bAAADoklEQVRoge2bT0s6QRyHP7slRiQdDIKKwqCC3kKXrr0Jt39IKoIQRkFFRFCnOmaHwEOXIujgoUNBQUWHgooOUiAdlmAhJMwsU3N+l5DAVndnZ+fXxj7gQdf9fuaZ+SKr4wqEEALOKIqCXC5nqIbb7YbL5VJ/A+HMzc0NcTqdBIChRzQarZojGpo2nRQKBUiShI+PD9OzuIotLi7i+vqaSxY3sYuLC6ysrPCK4yOWy+UgSRKKxSKPOACcxGZnZ5FIJHhElTFd7OTkBGtra2bHVGCqWDabxcjICEqlkpkxP2KqWCQSQTKZNDNCFdPEDg4OEI1GzSpfE1PE0uk0xsbGQPhfrZUxRSwcDkOWZTNKa4a5WDweRywWY11WN0zFUqkUfD4fy5LUMBULBAJQFIVlSWqYiW1vb2NnZ4dVOcMwEVMUBcFgkEUpZjAR8/l8SKVSLEoxw7BYLBZDPB5nMRamGBKTZRnhcJjVWJhCLUYIwejoKNLpNMvxMINabH19HYeHhyzHwhQqsWQyiampKdZjYYpusVKphOHhYWSzWd1hzc3NGBgY0H0eDbrFVldXcXp6ShU2Pj6O/v5+qnP1oksskUhgbm6OKsjhcCAUCnH7sNEsViwW4fV6qX+a9nq96OrqQiaToTpfL5rFlpeXcXl5SRXS2NiI+fl5AMDr6ytVDb1oEru6usLS0hJ1SCQSQWdnJwDg/f2duo4eaorl83lIkoR8Pk8V0NPTg+np6fJzo7ssWqkptrCwgNvbW6rigiBgY2MDDQ0N5dd4rVjVbaTz83NSV1dHvdXj9/sranZ0dBjeQoKGbSRVsbe3N9LX10cd7PF4SCaTqajb2trKRUy1FWdmZnB3d6dv+b8QBAGbm5toamqqOPb5+UlVUzc/2R4dHRFRFKlnMxAIqM6k2+3+P6348vJCPB4P8xbkLVbRipOTk3h4eKBafVEUVVuQO98t9/f3iSAI1LMYDAarziLPFSuLPT8/k/b2duqg7u7uqi3IW6zciqFQCI+Pj1Sr/qta8AsRAPb29rC1tUVdxO/3Y3BwkNWYmFD/9PSEiYkJ6gIulwtDQ0M4Pj7W9P5CoUCd9Z37+3vVTFEUgd3dXSY9/5seTqeT7z9zeGKLWQ1bzGrYYlbDFrMatpjV+LNi9S0tLVy/cpydnTG5wu/t7UVbW9uPxxwOB7j/7/6//ZjzV7DFrIYtZjVsMathi1kNW8xq/FkxgRC+92nIssxkH7rWXbX/AMX6E+KlShxnAAAAAElFTkSuQmCC"/></td></tr></table></span></p>
            </td>
            <td colspan="2">
                <p style="font-weight: bold;padding-top: 6pt;padding-left: 29pt;text-indent: 0pt;text-align: left;">
                    Programa Nacional de Estadísticas de Salud
                </p>
                <p style="font-weight: bold; padding-top: 6pt;padding-left: 29pt;text-indent: 0pt;text-align: left;">
                    Informe Diario de Consultas Médicas
                </p>
            </td>
            <td colspan="2"  style="text-align: right;">
                <p style="padding-top: 6pt;padding-left: 29pt;text-indent: 0pt;text-align: left; ">
                <?php if ($mismoDia) {?>
                    <b>Dia:</b><span class="s1"> <?= $diaD ?></span><b> Mes:</b><span class="s1"> <?= $mesD ?></span> <b> Año</b><span class="s1"> <?= $anioD ?> </span>
                <?php }else{ ?>
                    <b>Fecha:</b><span class="s1"> <?= $diaD .' '.$mesD.' '.$anioD ?> <b> a </b><span class="s1"> <?= $diaH.' '.$mesH.' '.$anioH ?> </span>
                <?php } ?>
                </p>
            </td>
        </tr>
    </table>
</div>
<div class="row">
    <div class="col-12">
        <p style="padding-top: 13pt;padding-left: 38pt;text-indent: 363pt;line-height: 200%;text-align: left;">
            <b>Apellidos y Nombre del Profesional (RRHH)</b><span class="s1">:<?= $nombreMedico?> </span>
        </p>
        <p style="padding-top: 13pt;line-height: 200%;text-align: center;">
            <b>Establecimiento (Efector):</b><span class="s1"><?= $nombreEfector ?></span> 
            <b>Departamento:</b><?= $nombreDepartamento ?> 
            <b>Servicio</b><span class="s1">: <?= $nombreServicio?></span> </p>
        </p>
    </div>
</div>
<table style="border-collapse:collapse;margin-left:3pt" cellspacing="0">
    <tr style="height:32pt">
        <td style="width:195pt;border-top-style:solid;border-top-width:1pt;border-left-style:solid;border-left-width:1pt;border-bottom-style:solid;border-bottom-width:1pt;border-right-style:solid;border-right-width:1pt">
            <p class="s2" style="padding-top: 10pt;padding-left: 51pt;text-indent: 0pt;text-align: left;font-weight: bold;">Apellidos y Nombres</p>
        </td>
        <td style="width:99pt;border-top-style:solid;border-top-width:1pt;border-left-style:solid;border-left-width:1pt;border-bottom-style:solid;border-bottom-width:1pt;border-right-style:solid;border-right-width:1pt">
            <p class="s2" style="padding-top: 10pt;text-indent: 0pt;text-align: center;font-weight: bold;">DNI</p>
        </td>
        <td style="width:159pt;border-top-style:solid;border-top-width:1pt;border-left-style:solid;border-left-width:1pt;border-bottom-style:solid;border-bottom-width:1pt;border-right-style:solid;border-right-width:1pt">
            <p class="s2" style="padding-top: 10pt;padding-left: 35pt;text-indent: 0pt;text-align: left;font-weight: bold;">Residencia Habitual</p>
        </td>
        <td style="width:70pt;border-top-style:solid;border-top-width:1pt;border-left-style:solid;border-left-width:1pt;border-bottom-style:solid;border-bottom-width:1pt;border-right-style:solid;border-right-width:1pt">
            <p style="text-indent: 0pt;text-align: left;">
                <br/>
            </p>
            <p class="s3" style="text-indent: 0pt;text-align: center;font-weight: bold;">O.S</p>
        </td>
        <td style="width:110pt;border-top-style:solid;border-top-width:1pt;border-left-style:solid;border-left-width:1pt;border-bottom-style:solid;border-bottom-width:1pt;border-right-style:solid;border-right-width:1pt">
            <p class="s4" style="padding-left: 1pt;text-indent: 0pt;line-height: 9pt;text-align: center;font-weight: bold;">Edad</p>
        </td>
        <td style="width:56pt;border-top-style:solid;border-top-width:1pt;border-left-style:solid;border-left-width:1pt;border-bottom-style:solid;border-bottom-width:1pt;border-right-style:solid;border-right-width:1pt">
            <p class="s6" style="padding-left: 17pt;text-indent: 0pt;line-height: 13pt;text-align: left;font-weight: bold;">Sexo</p>
        </td>
        <td style="width:232pt;border-top-style:solid;border-top-width:1pt;border-left-style:solid;border-left-width:1pt;border-bottom-style:solid;border-bottom-width:1pt;border-right-style:solid;border-right-width:1pt">
            <p class="s3" style="padding-left: 97pt;padding-right: 64pt;text-indent: -27pt;text-align: left;font-weight: bold;">Datos de la atención</p>
        </td>
        <td style="width:31pt;border-top-style:solid;border-top-width:1pt;border-left-style:solid;border-left-width:1pt;border-bottom-style:solid;border-bottom-width:1pt;border-right-style:solid;border-right-width:1pt">
            <p class="s4" style="padding-left: 6pt;padding-right: 4pt;text-indent: 0pt;text-align: left;font-weight: bold;">Ctrol. Emb.</p>
        </td>
        <td style="width:31pt;border-top-style:solid;border-top-width:1pt;border-left-style:solid;border-left-width:1pt;border-bottom-style:solid;border-bottom-width:1pt;border-right-style:solid;border-right-width:1pt">
            <p class="s4" style="padding-left: 6pt;padding-right: 4pt;text-indent: 0pt;text-align: left;font-weight: bold;">Fecha</p>
        </td>
    </tr>

    <?php 
            $countMasculinos = 0; $countFemenino = 0;$countT = 0;
             foreach($resultados as $record) {  
                $persona = new Persona();
                $modelPersona = $persona::findOne($record['id_persona']);
                $consulta = new Consulta();
                $modelConsulta = $consulta::findOne($record['id_consulta']);
                $motivosConsulta = $modelConsulta->motivoConsulta;
                $diagnosticosConsulta = $modelConsulta->diagnosticoConsultas;
                $practicasConsulta = $modelConsulta->consultaPracticas;
                $atencionesConsulta = $modelConsulta->atencionEnfermeria;
                $domicilio = ($modelPersona->getDomicilioActivo())? $modelPersona->getDomicilioActivo()->getDomicilioCompleto(): "No especificado.";

                $coberturas_api = [];   
                $cobertura_medica_key = sprintf("cobertura_medica_%s", $persona->id_persona);
                
                $mpi = new MpiApiController;
                $sexo_map = ['2'=>0, '1'=>1]; // 1 : femenino 2: masculino
                $persona_sexo = ArrayHelper::getValue($sexo_map, strtolower($modelPersona->sexo_biologico), 0);
                $coberturas_api = $mpi->get_cobertura_social($modelPersona->documento, $persona_sexo);
                $coberturas ="";                        
                if(count($coberturas_api) > 0) {
                    foreach ($coberturas_api as $key => $value) {
                        $coberturas .= " ".$value['nombre'];
                    }                      
                } 

                              
                switch ($modelPersona->sexo_biologico) {
                    case '2':
                        $countMasculinos++;
                        break;
                    case '1':
                        $countFemenino++;
                        break;                    
                    default:
                        $countT++;
                        break;
                }
       
            ?>
        <tr style="height:19pt">
            <td style="width:195pt;border-top-style:solid;border-top-width:1pt;border-left-style:solid;border-left-width:1pt;border-bottom-style:solid;border-bottom-width:1pt;border-right-style:solid;border-right-width:1pt">
                <?= $record['nombreyapellido']; ?>
            </td>
            <td style="width:99pt;border-top-style:solid;border-top-width:1pt;border-left-style:solid;border-left-width:1pt;border-bottom-style:solid;border-bottom-width:1pt;border-right-style:solid;border-right-width:1pt">
                <?= $record['documento']; ?>
            </td>
            <td style="width:159pt;border-top-style:solid;border-top-width:1pt;border-left-style:solid;border-left-width:1pt;border-bottom-style:solid;border-bottom-width:1pt;border-right-style:solid;border-right-width:1pt">
                <?= $domicilio ?>
            </td>
            <td style="overflow-wrap: break-word;width:70pt;border-top-style:solid;border-top-width:1pt;border-left-style:solid;border-left-width:1pt;border-bottom-style:solid;border-bottom-width:1pt;border-right-style:solid;border-right-width:1pt">
                <?= $coberturas ?>
            </td>
            <td style="width:110pt;border-top-style:solid;border-top-width:1pt;border-left-style:solid;border-left-width:1pt;border-bottom-style:solid;border-bottom-width:1pt;border-right-style:solid;border-right-width:1pt">
                <!-- Obtener edad en funcion de la fecha de nacimiento y la fecha de la consulta-->
                <?= $modelPersona->getEdad($record['fecha']); ?>
            </td>
            <td style="width:56pt;border-top-style:solid;border-top-width:1pt;border-left-style:solid;border-left-width:1pt;border-bottom-style:solid;border-bottom-width:1pt;border-right-style:solid;border-right-width:1pt">
                <?= $modelPersona->getSexoTexto(); ?>
            </td>
            <td style="width:232pt;border-top-style:solid;border-top-width:1pt;border-left-style:solid;border-left-width:1pt;border-bottom-style:solid;border-bottom-width:1pt;border-right-style:solid;border-right-width:1pt">
                <?php 
                if($motivosConsulta){
                    echo 'Motivo de consulta: ';
                    foreach ($motivosConsulta as $motivo) {                    
                        
                        echo $motivo->codigoSnomed->term.'<br>';
                    }
                }                    
                $diagnosticos = "";
                if($diagnosticosConsulta){                
                    echo 'Diagnosticos: ';                
                    foreach ($diagnosticosConsulta as $diagnostico) {                    
                        $diagnosticos .= " ". $diagnostico->codigoSnomed->term;
                        echo $diagnostico->codigoSnomed->term.'<br>';
                    }
                }
                
                if($practicasConsulta){                
                    echo 'Prácticas: ';                
                    foreach ($practicasConsulta as $practica) {                    
                        
                        echo $practica->codigoSnomed->term.'<br>';
                    }
                }
                if($atencionesConsulta){
                    echo 'Atenciones: ';
                    echo $atencionesConsulta->formatearDatos();
                }
                ?>

            </td>
            <td style="width:31pt;border-top-style:solid;border-top-width:1pt;border-left-style:solid;border-left-width:1pt;border-bottom-style:solid;border-bottom-width:1pt;border-right-style:solid;border-right-width:1pt; text-align:center;">
                <?php
                    $controlEmbarazo = '';
                    if (strripos($diagnosticos, 'embaraz') !== false) {
                        $controlEmbarazo = "X";
                    } 
                    echo $controlEmbarazo;
                ?>
            </td>
            <td style="width:31pt;border-top-style:solid;border-top-width:1pt;border-left-style:solid;border-left-width:1pt;border-bottom-style:solid;border-bottom-width:1pt;border-right-style:solid;border-right-width:1pt; text-align:center;">
                <?= date('d-m-Y', strtotime($record['fecha'])) ?>
            </td>
        </tr>
        <?php  }?>
</table>

<p style="text-indent: 0pt;text-align: left;">
    <br/>
</p>
<p style="padding-left: 74pt;text-indent: 0pt;text-align: left;">TOTALES</p>

<table style="border-collapse:collapse;margin-left:6.5pt" cellspacing="0">
    <tr>
        <td>M</td>
        <td>F</td>
        <td>T</td>
    </tr>
    <tr style="height:28pt">
        <td style="width:51pt;border-top-style:solid;border-top-width:1pt;border-left-style:solid;border-left-width:1pt;border-bottom-style:solid;border-bottom-width:1pt;border-right-style:solid;border-right-width:1pt">
            <p style="text-indent: 0pt;text-align: left;">
                <?= $countMasculinos?>
            </p>
        </td>
        <td style="width:49pt;border-top-style:solid;border-top-width:1pt;border-left-style:solid;border-left-width:1pt;border-bottom-style:solid;border-bottom-width:1pt;border-right-style:solid;border-right-width:1pt">
            <p style="text-indent: 0pt;text-align: left;">
            <?= $countFemenino?>
            </p>
        </td>
        <td style="width:58pt;border-top-style:solid;border-top-width:1pt;border-left-style:solid;border-left-width:1pt;border-bottom-style:solid;border-bottom-width:1pt;border-right-style:solid;border-right-width:1pt">
            <p style="text-indent: 0pt;text-align: left;">
            <?= $countMasculinos + $countFemenino?>
            </p>
        </td>
    </tr>
</table>
<h4 style="padding-left: 617pt;text-indent: 0pt;text-align: left;">Firma del Profesional</h4></body>