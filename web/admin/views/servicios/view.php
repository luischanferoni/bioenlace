<?php

use yii\helpers\Html;
use yii\widgets\DetailView;

/* @var $this yii\web\View */
/* @var $model common\models\Servicio */

$this->title = $model->id_servicio.' - '.$model->nombre;
$this->params['breadcrumbs'][] = ['label' => 'Servicios', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="servicio-view">

    <h1><?= Html::encode($this->title) ?></h1>

    <p>
        <?= Html::a('Actualizar', ['update', 'id' => $model->id_servicio], ['class' => 'btn btn-primary']) ?>
        <?= Html::a('Delete', ['delete', 'id' => $model->id_servicio], [
            'class' => 'btn btn-danger',
            'data' => [
                'confirm' => 'Realmente desea borrar este registro?',
                'method' => 'post',
            ],
        ]) ?>
    </p>

    <?= DetailView::widget([
        'model' => $model,
        'attributes' => [
            'id_servicio',
            'acepta_turnos',
            'acepta_practicas',
            'hallazgos_ecl',
            'medicamentos_ecl',
            'procedimientos_ecl',
        ],
    ]) ?>

    <p>
        <?= Html::a('Volver', ['index'], ['class' => 'btn btn-success']) ?>
    </p>
</div>
