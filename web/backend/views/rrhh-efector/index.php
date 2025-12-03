<?php

use yii\helpers\Html;
use yii\grid\GridView;

/* @var $this yii\web\View */
/* @var $searchModel common\models\busquedas\RrhhEfectorBusqueda */
/* @var $dataProvider yii\data\ActiveDataProvider */

$this->title = 'Recursos Humanos';
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="rrhh-efector-index">

    <h1><?= Html::encode($this->title) ?></h1>

    <?= GridView::widget([
        'dataProvider' => $dataProvider,
        'filterModel' => $searchModel,
        'columns' => [
            ['class' => 'yii\grid\SerialColumn'],
            [
                'attribute' => 'id_persona',
                'value' => function($data) {
                    return isset($data->persona) ? $data->persona->apellido.', '.$data->persona->nombre : '--';
                }
            ],

            [
                'attribute' => 'nombreEfector',
                'value' => function($data) {
                    return isset($data->efector) ? $data->efector->nombre : '--';
                },
                'visible' => Yii::$app->user->getIdEfector() ? false : true,
            ],

            [
                'class' => 'yii\grid\ActionColumn',
                'template' => '{delete}',
                'buttons'=> [
                    'view'  => function ($url, $model) {
                        return Html::a('Eliminar', ['delete', 'id' => $model->id_rr_hh], [
                            'class' => 'btn btn-danger',
                            'data' => [
                                'confirm' => 'Está seguro que desea eliminar este ítem?',
                                'method' => 'post',
                            ],
                        ]);
                    },                    
                ]                
            ],
        ],
    ]); ?>


</div>
