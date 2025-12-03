<?php
use yii\helpers\ArrayHelper;
use common\models\Persona;
use common\models\Consulta;
use common\models\ConsultaOdontologiaEstados;
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
                <p style="text-indent: 0pt;text-align: left; text-weight: bold; font-size:36px">
                    C7
                </p>
            </td>
            <td colspan="2">
                <p style="font-weight: bold;padding-top: 6pt;padding-left: 29pt;text-indent: 0pt;text-align: left;">
                    Programa Nacional de Estadísticas de Salud
                </p>
                <p style="font-weight: bold; padding-top: 6pt;padding-left: 29pt;text-indent: 0pt;text-align: left;">
                    Informe Diario Odontológico
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
            <b>Apellidos y Nombre del Médico (RRHH)</b><span class="s1">:<?= $nombreMedico?> </span>
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
        <td style="width:35pt;border-top-style:solid;border-top-width:1pt;border-left-style:solid;border-left-width:1pt;border-bottom-style:solid;border-bottom-width:1pt;border-right-style:solid;border-right-width:1pt">
            <p class="s2" style="padding-top: 10pt;padding-left: 51pt;text-indent: 0pt;text-align: left;font-weight: bold;">H.C N°</p>
        </td>
        <td style="width:150pt;border-top-style:solid;border-top-width:1pt;border-left-style:solid;border-left-width:1pt;border-bottom-style:solid;border-bottom-width:1pt;border-right-style:solid;border-right-width:1pt">
            <p class="s2" style="padding-top: 10pt;padding-left: 51pt;text-indent: 0pt;text-align: left;font-weight: bold;">Apellidos y Nombres</p>
        </td>
        <td style="width:70pt;border-top-style:solid;border-top-width:1pt;border-left-style:solid;border-left-width:1pt;border-bottom-style:solid;border-bottom-width:1pt;border-right-style:solid;border-right-width:1pt">
            <p class="s2" style="padding-top: 10pt;text-indent: 0pt;text-align: center;font-weight: bold;">DNI</p>
        </td>
        <td style="width:159pt;border-top-style:solid;border-top-width:1pt;border-left-style:solid;border-left-width:1pt;border-bottom-style:solid;border-bottom-width:1pt;border-right-style:solid;border-right-width:1pt">
            <p class="s2" style="padding-top: 10pt;padding-left: 35pt;text-indent: 0pt;text-align: left;font-weight: bold;">Residencia Habitual</p>
        </td>
        <td style="width:70pt;border-top-style:solid;border-top-width:1pt;border-left-style:solid;border-left-width:1pt;border-bottom-style:solid;border-bottom-width:1pt;border-right-style:solid;border-right-width:1pt">
            <p style="text-indent: 0pt;text-align: left;">
                <br/>
            </p>
            <p class="s3" style="padding-top: 10pt;padding-left: 35pt;text-indent: 0pt;text-align: center;font-weight: bold;">O.S</p>
        </td>
        <td style="width:30pt;border-top-style:solid;border-top-width:1pt;border-left-style:solid;border-left-width:1pt;border-bottom-style:solid;border-bottom-width:1pt;border-right-style:solid;border-right-width:1pt">
            <p class="s4" style="padding-left: 1pt;text-indent: 0pt;line-height: 9pt;text-align: center;font-weight: bold;">Edad</p>
        </td>
        <td style="width:56pt;border-top-style:solid;border-top-width:1pt;border-left-style:solid;border-left-width:1pt;border-bottom-style:solid;border-bottom-width:1pt;border-right-style:solid;border-right-width:1pt">
            <p class="s6" style="padding-left: 17pt;text-indent: 0pt;line-height: 13pt;text-align: left;font-weight: bold;">Sexo</p>
        </td>
        <td style="width:70pt;border-top-style:solid;border-top-width:1pt;border-left-style:solid;border-left-width:1pt;border-bottom-style:solid;border-bottom-width:1pt;border-right-style:solid;border-right-width:1pt">
            <p class="s3" style="padding-left: 97pt;padding-right: 64pt;text-indent: -27pt;text-align: left;font-weight: bold;">CPO/CEO</p>
        </td>
        <td style="width:150pt;border-top-style:solid;border-top-width:1pt;border-left-style:solid;border-left-width:1pt;border-bottom-style:solid;border-bottom-width:1pt;border-right-style:solid;border-right-width:1pt">
            <p class="s3" style="padding-left: 97pt;padding-right: 64pt;text-indent: -27pt;text-align: left;font-weight: bold;">DIAGNOSTICO</p>
        </td>
        <td style="width:120pt;border-top-style:solid;border-top-width:1pt;border-left-style:solid;border-left-width:1pt;border-bottom-style:solid;border-bottom-width:1pt;border-right-style:solid;border-right-width:1pt">
            <p class="s4" style="padding-left: 6pt;padding-right: 4pt;text-indent: 0pt;text-align: left;font-weight: bold;">Diente</p>
        </td>
        <td style="width:31pt;border-top-style:solid;border-top-width:1pt;border-left-style:solid;border-left-width:1pt;border-bottom-style:solid;border-bottom-width:1pt;border-right-style:solid;border-right-width:1pt">
            <p class="s4" style="padding-left: 6pt;padding-right: 4pt;text-indent: 0pt;text-align: left;font-weight: bold;">Código de Prest/Final</p>
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
                $diagnosticosConsulta = $modelConsulta->odontologiaDiagnosticos; //$modelConsulta->diagnosticoConsultas;
                $practicasOdontologicas = $modelConsulta->odontologiaPracticas;
                $domicilio = ($modelPersona->getDomicilioActivo())? $modelPersona->getDomicilioActivo()->getDomicilioCompleto(): "No especificado.";
                

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
            <td style="width:35pt;border-top-style:solid;border-top-width:1pt;border-left-style:solid;border-left-width:1pt;border-bottom-style:solid;border-bottom-width:1pt;border-right-style:solid;border-right-width:1pt"></td>
            <td style="width:150pt;border-top-style:solid;border-top-width:1pt;border-left-style:solid;border-left-width:1pt;border-bottom-style:solid;border-bottom-width:1pt;border-right-style:solid;border-right-width:1pt">
                <?= $record['nombreyapellido']; ?>
            </td>
            <td style="width:70pt;border-top-style:solid;border-top-width:1pt;border-left-style:solid;border-left-width:1pt;border-bottom-style:solid;border-bottom-width:1pt;border-right-style:solid;border-right-width:1pt">
                <?= $record['documento']; ?>
            </td>
            <td style="width:159pt;border-top-style:solid;border-top-width:1pt;border-left-style:solid;border-left-width:1pt;border-bottom-style:solid;border-bottom-width:1pt;border-right-style:solid;border-right-width:1pt">
                <?= $domicilio ?>
            </td>
            <td style="overflow-wrap: break-word;width:70pt;border-top-style:solid;border-top-width:1pt;border-left-style:solid;border-left-width:1pt;border-bottom-style:solid;border-bottom-width:1pt;border-right-style:solid;border-right-width:1pt">
                <?= $coberturas ?>
            </td>
            <td style="width:30pt;border-top-style:solid;border-top-width:1pt;border-left-style:solid;border-left-width:1pt;border-bottom-style:solid;border-bottom-width:1pt;border-right-style:solid;border-right-width:1pt">
                <!-- Obtener edad en funcion de la fecha de nacimiento y la fecha de la consulta-->
                &nbsp;<?= $modelPersona->getEdad($record['fecha']); ?>
            </td>
            <td style="width:56pt;border-top-style:solid;border-top-width:1pt;border-left-style:solid;border-left-width:1pt;border-bottom-style:solid;border-bottom-width:1pt;border-right-style:solid;border-right-width:1pt">
                <?= $modelPersona->getSexoTexto(); ?>
            </td>
            <td style="width:80pt;border-top-style:solid;border-top-width:1pt;border-left-style:solid;border-left-width:1pt;border-bottom-style:solid;border-bottom-width:1pt;border-right-style:solid;border-right-width:1pt; text-align:center;">
               <?php 
                    $cpo_ceo = ConsultaOdontologiaEstados::getCPOHastaConsulta($record['id_persona'], $record['id_consulta']); 
                
                    if ($modelPersona->getEdad($record['fecha']) < 6) {
                        echo "c:" . $cpo_ceo['c'] . " / e:" . $cpo_ceo['e'] . "/ o:" . $cpo_ceo['o'];
                    }

                    if ($modelPersona->getEdad($record['fecha']) < 15) {
                        echo "C:" . $cpo_ceo['C'] . " / P:" . $cpo_ceo['P'] . " / O:" . $cpo_ceo['O'];
                        echo "c:" . $cpo_ceo['c'] . " / e:" . $cpo_ceo['e'] . " / o:" . $cpo_ceo['o'];
                    } else {
                        echo "C:" . $cpo_ceo['C'] . " / P:" . $cpo_ceo['P'] . " / O:" . $cpo_ceo['O'];
                    }
                ?>
               
               <br/>
               
               
            </td>

            <td style="width:150pt;text-align:left;border-top-style:solid;border-top-width:1pt;border-left-style:solid;border-left-width:1pt;border-bottom-style:solid;border-bottom-width:1pt;border-right-style:solid;border-right-width:1pt">
                <?php 
                  
                $diagnosticos = "";
                if($diagnosticosConsulta){                                 
                    foreach ($diagnosticosConsulta as $diagnostico) {                                         
                        //$diagnosticos .= " ". $diagnostico->codigoSnomed->term;
                        $diagnosticos .= " ". $diagnostico->snomedDiagnostico->term ;
                        //$diagnosticos .= ' Pieza: '.$diagnostico->pieza ;
                        //$diagnosticos .= ($diagnostico->caras)? '('.$diagnostico->caras.')': '';
                        $diagnosticos .= '</br>';
                    }
                    echo $diagnosticos;
                }
                ?>

            </td>
            <td style="width:120pt;text-align:left;border-top-style:solid;border-top-width:1pt;border-left-style:solid;border-left-width:1pt;border-bottom-style:solid;border-bottom-width:1pt;border-right-style:solid;border-right-width:1pt;">
            <?php 
                  
                  $practicaDiente = "";
                  $practicaCodigo = "";
                  if($practicasOdontologicas){                                   
                      foreach ($practicasOdontologicas as $practica) {
                          $practicaDiente .= "Pieza: ". $practica->pieza;
                          $practicaDiente .= '</br>';
                          $practicaDiente .= ($practica->caras)? '('.$practica->caras.')' : '';                          
                          $practicaCodigo .= " ". $practica->codigo . ": " . $practica->snomedPractica->term.'</br>';
                      }
                  }
                  echo $practicaDiente;
                  ?>
            </td>
            <td style="width:100pt;text-align:left;border-top-style:solid;border-top-width:1pt;border-left-style:solid;border-left-width:1pt;border-bottom-style:solid;border-bottom-width:1pt;border-right-style:solid;border-right-width:1pt;">
                <?= $practicaCodigo ?>
            </td>
            <td style="width:31pt;border-top-style:solid;border-top-width:1pt;border-left-style:solid;border-left-width:1pt;border-bottom-style:solid;border-bottom-width:1pt;border-right-style:solid;border-right-width:1pt; text-align:center;">
                <?= date('d-m-Y', strtotime($record['fecha'])) ?>
            </td>

        </tr>
        <?php  }?>
        <tr>
            <td style="width:25pt;border-top-style:solid;border-top-width:1pt;border-left-style:solid;border-left-width:1pt;border-bottom-style:solid;border-bottom-width:1pt;border-right-style:solid;border-right-width:1pt; height: 50px;vertical-align:top;" colspan="6"><b>Observaciones</b></td>            
        </tr>
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
<h4 style="padding-left: 617pt;text-indent: 0pt;text-align: left;">Firma del Profesional</h4>



