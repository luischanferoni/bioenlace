<?php

use yii\helpers\Html;
use yii\grid\GridView;

/* @var $this yii\web\View */
/* @var $searchModel common\models\busquedas\ProfesionalSaludBusqueda */
/* @var $dataProvider yii\data\ActiveDataProvider */

$this->title = 'Profesional Saluds';
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="profesional-salud-index">

    <h1><?= Html::encode($this->title) ?></h1>

    <p>
        <?= Html::a('Create Profesional Salud', ['create'], ['class' => 'btn btn-success']) ?>
    </p>

    <?php // echo $this->render('_search', ['model' => $searchModel]); ?>

    <?= GridView::widget([
        'dataProvider' => $dataProvider,
        'filterModel' => $searchModel,
        'columns' => [
            ['class' => 'yii\grid\SerialColumn'],

            'id',
            'id_persona',
            'id_profesion',
            'id_especialidad',
            'eliminado',
            //'created_at',
            //'updated_at',
            //'deleted_at',
            //'created_by',
            //'updated_by',
            //'deleted_by',

            ['class' => 'yii\grid\ActionColumn'],
        ],
    ]); ?>


</div>
