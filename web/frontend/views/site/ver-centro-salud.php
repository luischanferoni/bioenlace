<?php

use yii\helpers\Html;
use yii\widgets\DetailView;


$this->title = $model->nombre;//yii::$app->user->getNombreEfector();
$this->params['breadcrumbs'][] = ['label' => 'Guía de Servicios y Centros de Salud', 'url' => ['guia-servicios']];
$this->params['breadcrumbs'][] = ['label' => 'Centros de Salud', 'url' => ['centros-salud',"id"=>$model->id_localidad]];
$this->params['breadcrumbs'][] = $this->title; 

?>
<style type="text/css">
    .head-grupo6  {
        color: #C65338;
        border-color:  #C65338;         
    }
    .grupo6 .panel-heading {        
        background-color: #F58634; 
        border-color: #F58634;
    }
    .grupo6 {
        border-color: #F58634;
    }
    
    .head-grupo8,.head-grupo1 {
        color: #B23437;
        border-color: #B23437;
    }
    .grupo8 .panel-heading,.grupo1 .panel-heading {
      background-color: #EC3237; 
        border-color: #EC3237;
    }
    .grupo8,.grupo1 {
        border-color: #EC3237;
    }

    .head-grupo3, .head-grupo9 {
        color: #075180;
        border-color: #075180;
    }
    .grupo3 .panel-heading,.grupo9 .panel-heading {
      background-color: #0060AA; 
        border-color: #0060AA;
    }   
    .grupo3,.grupo9 {
          border-color: #0060AA;
    }

    .head-grupo2  {
        color: #067A3B;
        border-color:  #067A3B;         
    }
    .grupo2 .panel-heading {        
        background-color: #00A85A; 
        border-color: #00A85A;
    }    
    .grupo2 {
        border-color: #00A85A;
    }

    .head-grupo4  {
        color: #EDBE00;
        border-color:  #EDBE00;         
    }
    .grupo4 .panel-heading {        
        background-color: #FFEA6F; 
        border-color: #FFEA6F;
    }
   .grupo4 {
        border-color: #FFEA6F;
    }
    
    .head-grupo7  {
        color: #35AEE5;
        border-color:  #35AEE5;         
    }
    .grupo7 .panel-heading {        
        background-color: #91D8F6; 
        border-color: #91D8F6;
    }
    .grupo7 {
        border-color: #91D8F6;
    }

    
    .head-grupo5  {
        color:  #881F5A;
        border-color:   #881F5A;         
    }
    .grupo5 .panel-heading {        
        background-color:  #A9518B; 
        border-color:  #A9518B;
    }
    .grupo5 {
        border-color: #A9518B; 
    }

      
        
</style>
<div class="efector-view">
    
    <h1 class="<?= 'head-grupo'.$model->grupo?>"><?= Html::encode($this->title) ?></h1>
    
<div class="panel panel-primary <?= 'grupo'.$model->grupo?>">
  <!-- Default panel contents -->
  <div class="panel-heading"><h3>Datos del Centro de Salud</h3></div>
  <div class="panel-body">
    <div class="container-fluid">
        <div class="row">
          <div class="col-md-3">Domicilio: </div>
          <div class="col-md-9"><?= $model->domicilio ?></div>
      </div>
      <div class="row">
          <div class="col-md-3">Formas de Acceso:</div>
          <div class="col-md-9"><?= $model->formas_acceso ?></div>
      </div>
      <div class="row">
          <div class="col-md-3">Telefonos:</div>
          <div class="col-md-9">
            <?= $model->telefono ?><br>
            <?= $model->telefono2 ?><br>
            <?= $model->telefono3 ?></div>
      </div>
      <div class="row">
          <div class="col-md-3">Direcciones de correo electrónico:</div>
          <div class="col-md-9">
            <?= $model->mail1 ?><br>
            <?= $model->mail2 ?><br>
            <?= $model->mail3 ?>
        </div>
        </div>
        <div class="row">
          <div class="col-md-3">Horarios de Atención:</div>
          <div class="col-md-9"><?= $model->dias_horario ?></div>
      </div>
      <div class="row">
          <div class="col-md-3">Turnos programados:</div>
          <div class="col-md-9"><span class="label label-danger">CONSULTAR</span></div>
      </div>
<!--div class="row">
  <div class="col-md-3">Laboratorio:</div>
  <div class="col-md-9"><?= $model->dias_horario ?></div>
</div>
<div class="row">
  <div class="col-md-3">Entrega de medicamentos:</div>
  <div class="col-md-9"><?= $model->dias_horario ?></div>
</div>
<div class="row">
  <div class="col-md-3">Vacunación:</div>
  <div class="col-md-9"><?= $model->dias_horario ?></div>
</div-->
</div>
</div>
</div>

<div class="panel panel-primary <?= 'grupo'.$model->grupo?>">
  <!-- Default panel contents -->
  <div class="panel-heading">Servicios y Especialidades</div>
  <div class="panel-body">
    <!-- Table -->
    <table class="table table-striped">
        <tr>
            <th>#</th>
            <th>Servicio</th>
            <th>Horarios</th>
        </tr>
        <?php 
        $i=0;
        foreach ($model->serviciosEfectors as $servicio) { 
            if($servicio->horario != '') {
                ?>
                <tr>
                    <td><?= ++ $i ?></td>
                    <td><?= ($servicio->nombreServicio == 'ENFERMERIA')?'ENFERMERIA / VACUNATORIO':$servicio->nombreServicio ?></td>
                    <td><?= $servicio->horario ?></td>
                </tr>   
            <?php } } ?>

        </table>
    </div> 

</div>
