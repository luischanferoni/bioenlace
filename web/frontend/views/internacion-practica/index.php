<?php

use yii\helpers\Html;
use yii\grid\GridView;

/* @var $this yii\web\View */
/* @var $searchModel common\models\busquedas\SegNivelInternacionPracticaBusqueda */
/* @var $dataProvider yii\data\ActiveDataProvider */

$this->title = 'Prácticas';
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="seg-nivel-internacion-practica-index">

    <h1><?= Html::encode($this->title) ?></h1>

    <p>
        <?= Html::a('Crear Práctica', ['create'], ['class' => 'btn btn-success']) ?>
    </p>

    <?php // echo $this->render('_search', ['model' => $searchModel]); ?>

    <?= GridView::widget([
        'dataProvider' => $dataProvider,
        'filterModel' => $searchModel,
        'columns' => [
            ['class' => 'yii\grid\SerialColumn'],

            'id',
            'conceptId',
            'resultado',
            'informe:ntext',
            'id_rrhh_solicita',
            //'id_rrhh_realiza',
            //'id_internacion',

            ['class' => 'yii\grid\ActionColumn'],
        ],
    ]); ?>


</div>
