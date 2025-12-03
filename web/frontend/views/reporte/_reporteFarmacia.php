<?php

use yii\helpers\ArrayHelper;
use common\models\Persona;
use common\models\Consulta;
use frontend\controllers\MpiApiController;
use common\models\RrhhEfector;
use common\models\Servicio;

?>


<div class="row">
    <table>
        <tr>
            <td colspan="2">
                <p style="font-weight: bold">Ministerio de Salud y Desarrollo Social</p>
                <p style="font-weight: bold">Secretaria Técnica de Estadística de Salud </p>
            </td>
            <td colspan="1">

            </td>
            <td colspan="2">
                <p style="font-weight: bold;padding-top: 6pt;padding-left: 29pt;text-indent: 0pt;text-align: left;">
                    Programa Nacional de Estadísticas de Salud
                </p>
                <p style="font-weight: bold; padding-top: 6pt;padding-left: 29pt;text-indent: 0pt;text-align: left;">
                    Informe Diario de Prescripciones Médicas
                </p>
            </td>
            <td colspan="2" style="text-align: right;">
                <p style="padding-top: 6pt;padding-left: 29pt;text-indent: 0pt;text-align: left; ">
                    <b>Dia:</b><span class="s1"> <?= $dia ?></span><b> Mes:</b><span class="s1"> <?= $mes ?></span> <b> Año</b><span class="s1"> <?= $anio ?> </span>
                </p>
            </td>
        </tr>
        <tr>
            <td colspan="8" style="text-align:center; text-transform:uppercase;">
                <h3><b>Reporte de Medicamentos prescriptos</b></h3>
            </td>
        </tr>
    </table>
</div>
<div class="row">
    <div class="col-12">
        <p style="padding-top: 13pt;line-height: 200%;text-align: center;">
            <b>Establecimiento (Efector):</b><span class="s1"><?= $nombreEfector ?></span>
            <b>Departamento:</b><?= $nombreDepartamento ?>
            <b>Servicio</b><span class="s1">: <?= $nombreServicio ?></span>
        </p>
        </p>
    </div>
</div>
<table style="border-collapse:collapse;margin-left:3pt" cellspacing="0">
    <tr style="height:32pt">
        <td style="width:195pt;border-top-style:solid;border-top-width:1pt;border-left-style:solid;border-left-width:1pt;border-bottom-style:solid;border-bottom-width:1pt;border-right-style:solid;border-right-width:1pt">
            <p class="s2" style="padding-top: 10pt;padding-left: 51pt;text-indent: 0pt;text-align: left;font-weight: bold;">Apellidos y Nombres</p>
        </td>
        <td style="width:79pt;border-top-style:solid;border-top-width:1pt;border-left-style:solid;border-left-width:1pt;border-bottom-style:solid;border-bottom-width:1pt;border-right-style:solid;border-right-width:1pt">
            <p class="s2" style="padding-top: 10pt;text-indent: 0pt;text-align: center;font-weight: bold;">DNI</p>
        </td>
        <td style="width:159pt;border-top-style:solid;border-top-width:1pt;border-left-style:solid;border-left-width:1pt;border-bottom-style:solid;border-bottom-width:1pt;border-right-style:solid;border-right-width:1pt">
            <p class="s2" style="padding-top: 10pt;padding-left: 35pt;text-indent: 0pt;text-align: left;font-weight: bold;">Residencia Habitual</p>
        </td>
        <td style="width:70pt;border-top-style:solid;border-top-width:1pt;border-left-style:solid;border-left-width:1pt;border-bottom-style:solid;border-bottom-width:1pt;border-right-style:solid;border-right-width:1pt">
            <p style="text-indent: 0pt;text-align: left;">
                <br />
            </p>
            <p class="s3" style="text-indent: 0pt;text-align: center;font-weight: bold;">O.S</p>
        </td>
        <td style="width:40pt;border-top-style:solid;border-top-width:1pt;border-left-style:solid;border-left-width:1pt;border-bottom-style:solid;border-bottom-width:1pt;border-right-style:solid;border-right-width:1pt">
            <p class="s4" style="padding-left: 1pt;text-indent: 0pt;line-height: 9pt;text-align: center;font-weight: bold;">Edad</p>
        </td>
        <td style="width:56pt;border-top-style:solid;border-top-width:1pt;border-left-style:solid;border-left-width:1pt;border-bottom-style:solid;border-bottom-width:1pt;border-right-style:solid;border-right-width:1pt">
            <p class="s6" style="padding-left: 17pt;text-indent: 0pt;line-height: 13pt;text-align: left;font-weight: bold;">Sexo</p>
        </td>
        <td style="width:232pt;border-top-style:solid;border-top-width:1pt;border-left-style:solid;border-left-width:1pt;border-bottom-style:solid;border-bottom-width:1pt;border-right-style:solid;border-right-width:1pt">
            <p class="s3" style="padding-left: 97pt;padding-right: 64pt;text-indent: -27pt;text-align: left;font-weight: bold;">Diagnóstico y Medicamentos</p>
        </td>
        <td style="width:131pt;border-top-style:solid;border-top-width:1pt;border-left-style:solid;border-left-width:1pt;border-bottom-style:solid;border-bottom-width:1pt;border-right-style:solid;border-right-width:1pt">
            <p class="s4" style="padding-left: 6pt;padding-right: 4pt;text-indent: 0pt;text-align: left;font-weight: bold;">Profesional</p>
        </td>
        <td style="width:31pt;border-top-style:solid;border-top-width:1pt;border-left-style:solid;border-left-width:1pt;border-bottom-style:solid;border-bottom-width:1pt;border-right-style:solid;border-right-width:1pt">
            <p class="s4" style="padding-left: 6pt;padding-right: 4pt;text-indent: 0pt;text-align: left;font-weight: bold;">Fecha</p>
        </td>
    </tr>

    <?php
    $countMasculinos = 0;
    $countFemenino = 0;
    $countT = 0;
    foreach ($resultados as $record) {
        $persona = new Persona();
        $modelPersona = $persona::findOne($record['id_persona']);
        $consulta = new Consulta();
        $modelConsulta = $consulta::findOne($record['id_consulta']);
        $medicamentosConsulta = $modelConsulta->consultaMedicamentos;
        $diagnosticosConsulta = $modelConsulta->diagnosticoConsultas;
        $practicasConsulta = $modelConsulta->consultaPracticas;
        $domicilio = ($modelPersona->getDomicilioActivo()) ? $modelPersona->getDomicilioActivo()->getDomicilioCompleto() : "No especificado.";
        $nombreMedico = RrhhEfector::findOne(['id_rr_hh' => $modelConsulta->id_rr_hh])->persona->getNombreCompleto('');
        $coberturas_api = [];
        $cobertura_medica_key = sprintf("cobertura_medica_%s", $persona->id_persona);

        $mpi = new MpiApiController;
        $sexo_map = ['2' => 0, '1' => 1]; // 1 : femenino 2: masculino
        $persona_sexo = ArrayHelper::getValue($sexo_map, strtolower($modelPersona->sexo_biologico), 0);
        $coberturas_api = $mpi->get_cobertura_social($modelPersona->documento, $persona_sexo);
        $coberturas = "";
        if (count($coberturas_api) > 0) {
            foreach ($coberturas_api as $key => $value) {
                $coberturas .= " " . $value['nombre'];
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
            <td style="overflow-wrap: break-word;width:100pt;border-top-style:solid;border-top-width:1pt;border-left-style:solid;border-left-width:1pt;border-bottom-style:solid;border-bottom-width:1pt;border-right-style:solid;border-right-width:1pt">
                <?= $coberturas ?>
            </td>
            <td style="width:40pt;border-top-style:solid;border-top-width:1pt;border-left-style:solid;border-left-width:1pt;border-bottom-style:solid;border-bottom-width:1pt;border-right-style:solid;border-right-width:1pt">
                <!-- Obtener edad en funcion de la fecha de nacimiento y la fecha de la consulta-->
                <?= $modelPersona->getEdad($record['fecha']); ?>
            </td>
            <td style="width:56pt;border-top-style:solid;border-top-width:1pt;border-left-style:solid;border-left-width:1pt;border-bottom-style:solid;border-bottom-width:1pt;border-right-style:solid;border-right-width:1pt">
                <?= $modelPersona->getSexoTexto(); ?>
            </td>
            <td style="width:232pt;border-top-style:solid;border-top-width:1pt;border-left-style:solid;border-left-width:1pt;border-bottom-style:solid;border-bottom-width:1pt;border-right-style:solid;border-right-width:1pt">
                <?php
                $diagnosticos = '';
                foreach ($diagnosticosConsulta as $diagnostico) {
                    $diagnosticos .= '<p><h4>' . $diagnostico->codigoSnomed->term . '</h4>';
                    if ($diagnostico->medicamentos) {
                        $diagnosticos .= '<ol>';
                        foreach ($diagnostico->medicamentos as $medicamento) {
                            $diagnosticos .= '<li>' . $medicamento->snomedMedicamento->term . '</li>';
                        }
                        $diagnosticos .= '</ol>';
                    } else {
                        $diagnosticos .= '<p><b>sin tratamiento indicado</b></p>';
                    }
                    $diagnosticos .= '</p>';
                }
                echo $diagnosticos;

                $medicamentos = '';

                if ($nombreServicio == 'FARMACIA') {

                    foreach ($practicasConsulta as $practica) {

                        if ($practica->codigo == '373784005') {

                            $medicamentos .= '<p>' . $practica->informe . '</p>';
                        }
                    }
                }

                echo $medicamentos;

                ?>

            </td>
            <td style="width:101pt;border-top-style:solid;border-top-width:1pt;border-left-style:solid;border-left-width:1pt;border-bottom-style:solid;border-bottom-width:1pt;border-right-style:solid;border-right-width:1pt;">
                <?= $nombreMedico ?>
            </td>
            <td style="width:31pt;border-top-style:solid;border-top-width:1pt;border-left-style:solid;border-left-width:1pt;border-bottom-style:solid;border-bottom-width:1pt;border-right-style:solid;border-right-width:1pt; text-align:center;">
                <?= date('d-m-Y', strtotime($record['fecha'])) ?>
            </td>
        </tr>
    <?php  } ?>
</table>

<p style="text-indent: 0pt;text-align: left;">
    <br />
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
                <?= $countMasculinos ?>
            </p>
        </td>
        <td style="width:49pt;border-top-style:solid;border-top-width:1pt;border-left-style:solid;border-left-width:1pt;border-bottom-style:solid;border-bottom-width:1pt;border-right-style:solid;border-right-width:1pt">
            <p style="text-indent: 0pt;text-align: left;">
                <?= $countFemenino ?>
            </p>
        </td>
        <td style="width:58pt;border-top-style:solid;border-top-width:1pt;border-left-style:solid;border-left-width:1pt;border-bottom-style:solid;border-bottom-width:1pt;border-right-style:solid;border-right-width:1pt">
            <p style="text-indent: 0pt;text-align: left;">
                <?= $countMasculinos + $countFemenino ?>
            </p>
        </td>
    </tr>
</table>
<h4 style="padding-left: 617pt;text-indent: 0pt;text-align: left;">Firma del Médico</h4>
</body>