<?php
use yii\helpers\Html;
use yii\grid\GridView;
use yii\helpers\ArrayHelper;


$this->title = 'Internaciones Finalizadas';
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="internaciones-finalizadas">
    <h1><?= Html::encode($this->title) ?></h1>
    <?= GridView::widget([
        'dataProvider' => $dataProvider,
        'filterModel' => $searchModel,
        'columns' => [
            ['class' => 'yii\grid\SerialColumn'],

            //'id',
            [
                'label' => 'Paciente',
                'attribute' => 'id_persona',
                'value' => function ($data) {                   
                    return $data->paciente? $data->paciente->nombreCompleto: 'No definida';
                }
            ],
            'fecha_inicio',
            'hora_inicio',
            'tipo_ingreso',
            'fecha_fin',
            'hora_fin',
            [
                'label' => 'Tipo de alta',
                'attribute' => 'id_tipo_alta',
                'value' => function ($data) {                   
                    return $data->tipoAlta? $data->tipoAlta->tipo_alta: 'No definida';
                }
            ],

            //['class' => 'yii\grid\ActionColumn'],
        ],
    ]); ?>


</div>
