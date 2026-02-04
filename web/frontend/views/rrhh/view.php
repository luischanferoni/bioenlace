<?php

use yii\helpers\Html;
use yii\widgets\DetailView;

/* @var $this yii\web\View */
/* @var $model common\models\RrhhEfector */

$persona = $model->persona;
$this->title = 'RRHH: ' . ($persona ? $persona->apellido . ', ' . $persona->nombre : $model->id_rr_hh);
$this->params['breadcrumbs'][] = ['label' => 'RRHH', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="rrhh-view">

    <h1><?= Html::encode($this->title) ?></h1>

    <p>
        <?= Html::a('Actualizar', ['update', 'id' => $model->id_rr_hh], ['class' => 'btn btn-primary']) ?>
        <?= Html::a('Borrar', ['delete', 'id' => $model->id_rr_hh], [
            'class' => 'btn btn-danger',
            'data' => [
                'confirm' => '¿Realmente desea borrar este registro?',
                'method' => 'post',
            ],
        ]) ?>
    </p>

    <div class="panel panel-info">
        <div class="panel-heading">Datos del Recurso Humano</div>
        <div class="panel-body">
            <div class="row">
                <div class="col-sm-4"><label>Apellido y Nombre:</label> <?= $persona ? $persona->apellido . ', ' . $persona->nombre : '-' ?></div>
                <div class="col-sm-4"><label>Efector:</label> <?= $model->efector ? $model->efector->nombre : '-' ?></div>
                <div class="col-sm-4"><label>Servicios:</label> <?php
                    $servicios = [];
                    foreach ($model->rrhhServicio as $rs) {
                        if ($rs->servicio) $servicios[] = $rs->servicio->nombre;
                    }
                    echo implode(', ', $servicios) ?: '-';
                ?></div>
            </div>
        </div>
    </div>
</div>
