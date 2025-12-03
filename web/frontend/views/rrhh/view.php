<?php

use yii\helpers\Html;
use yii\widgets\DetailView;

/* @var $this yii\web\View */
/* @var $model common\models\Rrhh */

$persona = common\models\Persona::findOne($model->id_persona);
$this->title = 'Datos RRHH de : ' .$persona->apellido.', '.$persona->nombre;
$this->params['breadcrumbs'][] = ['label' => 'Rrhhs', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
//print_r($mje);
?>
<div class="rrhh-view">

    <h1><?= Html::encode($this->title) ?></h1>

    <p>
        <?= Html::a('Actualizar', ['update', 'id' => $model->id_rr_hh , 'idp' => $model->id_persona], ['class' => 'btn btn-primary']) ?>
        <?= Html::a('Borrar', ['delete', 'id' => $model->id_rr_hh], [
            'class' => 'btn btn-danger',
            'data' => [
                'confirm' => 'Realmente desea borrar este registro?',
                'method' => 'post',
            ],
        ]) ?>
    </p>

    <div class="panel panel-info">
        <div class="panel-heading">Datos del Recurso Humano</div>
        <div class="panel-body">
            <div class="row">
                <div class="col-sm-4"><label>Apellido y Nombre:</label> <?= $model->persona->apellido . ', ' . $model->persona->nombre ?></div>        
                <div class="col-sm-4"><label>Profesión:</label> <?= $model->profesion->nombre ?></div>
                <div class="col-sm-4"><label>Especialidad:</label> <?= (is_object($model->especialidad))?$model->especialidad->nombre: 'No definido' ?></div>            
            </div>                   
        </div>
    </div>
    <div class="panel panel-info">
        <div class="panel-heading">Efectores</div>
        <div class="panel-body">
            <?php 

            foreach ($model->rrhhEfector as $key => $value) { ?>
            <div class="row">
                <div class="col-sm-4"><label>Efector:</label> <?= $value->efector->nombre ?></div>        
                <div class="col-sm-4"><label>Servicio:</label> <?= $value->servicio->nombre ?></div>
                <div class="col-sm-4"><label>Condición Laboral:</label> <?= (is_object($value->idCondicionLaboral))?$value->idCondicionLaboral->nombre: 'No definido' ?></div>            
            </div>  
            <?php } ?>
        </div>
    </div>
</div>
