<?php

use yii\helpers\Html;
use yii\grid\GridView;

/* @var $this yii\web\View */
/* @var $searchModel common\models\busquedas\RrhhEfectorBusqueda */
/* @var $dataProvider yii\data\ActiveDataProvider */

$this->title = 'Recursos humanos por efector';
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="rrhh-index">

    <h1><?= Html::encode($this->title) ?></h1>

    <p>
        <?= Html::a('Nuevo RRHH', ['create'], ['class' => 'btn btn-success']) ?>
    </p>

    <?= GridView::widget([
        'dataProvider' => $dataProvider,
        'filterModel' => $searchModel,
        'columns' => [
            ['class' => 'yii\grid\SerialColumn'],
            [
                'label' => 'Apellido',
                'attribute' => 'nombrePersona',
                'value' => 'persona.apellido',
            ],
            [
                'label' => 'Nombre',
                'value' => 'persona.nombre',
            ],
            [
                'label' => 'Efector',
                'value' => 'efector.nombre',
            ],
            ['class' => 'yii\grid\ActionColumn', 'urlCreator' => function ($action, $model, $key, $index) {
                if ($action === 'view') return ['view', 'id' => $model->id_rr_hh];
                if ($action === 'update') return ['update', 'id' => $model->id_rr_hh];
                if ($action === 'delete') return ['delete', 'id' => $model->id_rr_hh];
                return '#';
            }],
        ],
    ]); ?>

</div>
