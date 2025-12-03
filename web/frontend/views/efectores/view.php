<?php

use yii\helpers\Html;
use yii\widgets\DetailView;


$this->title = Yii::$app->user->nombreEfector;//yii::$app->user->getNombreEfector();
$this->params['breadcrumbs'][] = ['label' => 'Efectores', 'url' => ['indexuserefector']];
$this->params['breadcrumbs'][] = $this->title; 

?>
<div class="efector-view">

    <h1><?= Html::encode($this->title) ?></h1>

    <p>
        <?= Html::a('Actualizar', ['update', 'id' => $model->id_efector], ['class' => 'btn btn-primary']) ?>
        <?= Html::a('Eliminar', ['delete', 'id' => $model->id_efector], [
            'class' => 'btn btn-danger',
            'data' => [
                'confirm' => 'Esta seguro que desea eliminar este item?',
                'method' => 'post',
            ],
        ]) ?>
    </p>

    <?= DetailView::widget([
        'model' => $model,
        'attributes' => [
            'id_efector',
            'codigo_sisa',
            'nombre',
            'dependencia',
            'tipologia',
            'domicilio',
            'grupo',
            'formas_acceso',
            'telefono',
            'telefono2',
            'telefono3',
            'mail1',
            'mail2',
            'mail3',
            'dias_horario',
            'origen_financiamiento',
            'id_localidad',
            'estado',
        ],
    ]) ?>

</div>
