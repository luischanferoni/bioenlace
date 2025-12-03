<?php

use yii\helpers\Html;
use yii\grid\GridView;

/* @var $this yii\web\View */
/* @var $searchModel common\models\busquedas\SegNivelInternacionDiagnosticoBusqueda */
/* @var $dataProvider yii\data\ActiveDataProvider */

$this->title = 'Internación - Diagnósticos';
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="seg-nivel-internacion-diagnostico-index">

    <h1><?= Html::encode($this->title) ?></h1>


    <?php // echo $this->render('_search', ['model' => $searchModel]); ?>

    <?= GridView::widget([
        'dataProvider' => $dataProvider,
        'filterModel' => $searchModel,
        'columns' => [
            ['class' => 'yii\grid\SerialColumn'],

            'id',
            'id_internacion',
            'conceptId',

            ['class' => 'yii\grid\ActionColumn'],
        ],
    ]); ?>


</div>
