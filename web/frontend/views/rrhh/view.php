<?php

use yii\helpers\Html;

/* @var $this yii\web\View */
/* @var $model common\models\ProfesionalEfectorServicio */

$persona = $model->persona;
$this->title = 'PES #' . $model->id . ': ' . ($persona ? $persona->apellido . ', ' . $persona->nombre : '');
$this->params['breadcrumbs'][] = ['label' => 'RRHH', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="rrhh-view">

    <h1><?= Html::encode($this->title) ?></h1>

    <p>
        <?= Html::a('Actualizar', ['update', 'id' => $model->id], ['class' => 'btn btn-primary']) ?>
        <?= Html::a('Borrar', ['delete', 'id' => $model->id], [
            'class' => 'btn btn-danger',
            'data' => [
                'confirm' => '¿Realmente desea borrar este registro?',
                'method' => 'post',
            ],
        ]) ?>
    </p>

    <div class="panel panel-info">
        <div class="panel-heading">Asignación profesional–efector–servicio</div>
        <div class="panel-body">
            <div class="row">
                <div class="col-sm-4"><label>Apellido y Nombre:</label> <?= $persona ? $persona->apellido . ', ' . $persona->nombre : '-' ?></div>
                <div class="col-sm-4"><label>Efector:</label> <?= $model->efector ? $model->efector->nombre : '-' ?></div>
                <div class="col-sm-4"><label>Servicio:</label> <?= $model->servicio ? $model->servicio->nombre : '-' ?></div>
            </div>
        </div>
    </div>
</div>
