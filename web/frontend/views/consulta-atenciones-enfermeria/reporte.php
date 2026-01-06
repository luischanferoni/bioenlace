<?php

use yii\helpers\Html;
use yii\grid\GridView;

/* @var $this yii\web\View */
/* @var $model common\models\Personas */

$this->title = 'Planilla de Resumen Mensual de Prestaciones de Enfermería - APS';
$this->params['breadcrumbs'][] = ['label' => 'Reportes', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;

$meses = [
            '01' => 'Enero', '02' => 'Febrero', '03' => 'Marzo',
            '04' => 'Abril', '05' => 'Mayo', '06' => 'Junio',
            '07' => 'Julio', '08' => 'Agosto', '09' => 'Septiembre',
            '10' => 'Octubre', '11' => 'Noviembre', '12' => 'Diciembre',
         ];
$mes = Yii::$app->request->get('mes');
$anio = Yii::$app->request->get('anio');
?>
<style>
    .texto-pequenio th {
        font-size: 9pt;
    }
</style>
<header class="header">
<div class="row">
  <div class="col-4 text-center">
    <img src="https://msaludsgo.gov.ar/web/wp-content/themes/msaludsgo/img/Logo-Minis.png" style="height: 95px;"/>
  </div>
  <div class="col-4 text-center">
    <img class="pull-right" src="<?= Yii::getAlias('@web')?>/images/logo_aps.jpeg" style="height: 95px;"/>
  </div>
  <div class="col-4 text-center">  
    <img class="" src="<?= Yii::getAlias('@web') ?>/images/logoSISSE2_small.png" style="height: 95px;"/>
  </div>
</div>
<div class="row">
    <div class="col-12">
        <p class="text-center">
          MINISTERIO DE SALUD - PROVINCIA DE SANTIAGO DEL ESTERO<br/>
          DIRECCI&Oacute;N DE ATENCI&Oacute;N PRIMARIA DE LA SALUD<br/>
          &Aacute;REA DE ENFERMER&Iacute;A<br/>
          BIOENLACE <?=Yii::$app->user->getNombreEfector()?>
        </p> 
    </div>
</div>  
 
</header>
<div class="clearfix"></div>
<div class="wrap">
<div class="reportes-edad">
    <h3><?= Html::encode($this->title) ?></h3>
    <div class="row">
        <div class="col-sm-12"><label>Establecimiento:</label> <?=Yii::$app->user->getNombreEfector()?></div>
    </div>
    <div class="row">
        <div class="col-sm-12"><label>Departamento:</label> </div>
    </div>
    <div class="row">
        <div class="col-sm-4"><label>Periodo:</label></div>
        <div class="col-sm-4"><label>Mes:</label> <?= $meses[$mes] ?></div>
        <div class="col-sm-4"><label>Año:</label> <?=$anio?></div>
    </div>
                <table class="table table-bordered texto-pequenio">
                    <tr>
                        <td colspan="18" class="text-center">Pr&aacute;cticas Asistenciales Intramuro y Extramuros</td>
                    </tr>
                    <tr>
                        <th colspan="2"></th>
                        <th colspan="16" class="text-center">Totales</th>
                    </tr>
                    <tr>                        
                        <th>Días de Atención</th>
                        <th>Personas Asistidas</th>
                        <th>Control T/A</th> 
                        <th>Control P.C.</th>
                        <th>Control Temperatura</th>        
                        <th>Nebulizaciones</th>        
                        <th>Rescate y SBO</th>        
                        <th>Control G.C.</th>        
                        <th>Control P/T</th>        
                        <th>Control C/A</th>        
                        <th>Control A.V.</th>        
                        <th>Inyectables</th>        
                        <th>Inmunizaciones</th>        
                        <th>E.P.</th>        
                        <th>Curaciones</th>        
                        <th>I.A.</th>
                        <th>visitas domiciliarias</th>
                        <th>ECG</th>
                    </tr>
                <?php 
                foreach ($resultados as $value) { ?>
                    <tr>
                        <td><?= $value['cant_dias'] ?></td>
                        <td><?= $value['total'] ?></td>
                        <td><?= $value['TA'] ?></td>
                        <td><?= $value['per'] ?></td>
                        <td><?= $value['temp'] ?></td>
                        <td><?= $value['neb'] ?></td>
                        <td><?= $value['RS'] ?></td>
                        <td><?= $value['GC'] ?></td>
                        <td><?= $value['PT'] ?></td>
                        <td><?= $value['CA'] ?></td>
                        <td><?= $value['AV'] ?></td>
                        <td><?= $value['iny'] ?></td>
                        <td><?= $value['inm'] ?></td>
                        <td><?= $value['EP'] ?></td>        
                        <td><?= $value['cur'] ?></td>
                        <td><?= $value['IA'] ?></td>        
                        <td><?= $value['VD'] ?></td>
                        <td><?= $value['ECG'] ?></td>
                    </tr>
                <?php } ?>
                </table>
      
                <div class="row">
                    <div class="col-12">
                        <h3>Referencias</h3>
                        <div class="row">
                            <div class="col-2">
                                <ul>
                                    <li>T/A: Tensión Arterial</li>
                                    <li>P.C.: Perimetro Cefalico</li>
                                    <li>G.C.: Glucemia Capilar</li>
                                </ul>
                            </div>
                            <div class="col-2">
                                <ul>                                    
                                    <li>P/T: Peso y talla</li>
                                    <li>C/A: Circunferencia Abdominal</li>
                                    <li>A.V.: Agudeza Visual</li>
                                </ul>
                            </div>
                            <div class="col-2">
                                <ul>                                    
                                    <li>E.P.: Extracción de Puntos</li>
                                    <li>I.A.: Internación Abreviada</li>
                                    <li>ECG: Electrocardiograma</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
        
</div>
</div>