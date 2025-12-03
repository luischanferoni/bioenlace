<?php

use yii\helpers\Html;
use yii\grid\GridView;

/* @var $this yii\web\View */
/* @var $searchModel common\models\busquedas\NovedadBusqueda */
/* @var $dataProvider yii\data\ActiveDataProvider */

$this->title = 'Novedades';
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="novedad-index">
    <div class="card">
        <div class="card-header">
            <div class="header-title d-flex align-items-self justify-content-between">
                <h1><?= Html::encode($this->title) ?></h1>

            </div>
        </div>
    <?php // echo $this->render('_search', ['model' => $searchModel]); ?>
    <div class="card-body">
    <?= GridView::widget([
        'dataProvider' => $dataProvider,
        'filterModel' => $searchModel,
        'columns' => [
            ['class' => 'yii\grid\SerialColumn'],

            'titulo',
            [
                'format'=>"ntext", // or other formatter
                'attribute' => 'texto',
                'contentOptions' => ['class' => 'text-wrap'], // For TD
         
            ],

            [
                'class' => 'yii\grid\ActionColumn',
                'template' => '{view}'
            ],
        ],
    ]); ?>


</div>
</div>
