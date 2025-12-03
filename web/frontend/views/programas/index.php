<?php

use yii\helpers\Html;
use yii\grid\GridView;

/* @var $this yii\web\View */
/* @var $searchModel common\models\busquedas\ProgramasBusquedas */
/* @var $dataProvider yii\data\ActiveDataProvider */

$this->title = 'Programas de Salud';
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="programa-index">

    <h1><?= Html::encode($this->title) ?></h1>
    <?php // echo $this->render('_search', ['model' => $searchModel]); ?>

    <p>
        <?= Html::a('Nuevo Programa', ['create'], ['class' => 'btn btn-success']) ?>
    </p>

    <?= GridView::widget([
        'dataProvider' => $dataProvider,
        'filterModel' => $searchModel,
        'columns' => [
            ['class' => 'yii\grid\SerialColumn'],

            [
                'label' => 'ID Programa',
                'attribute' => 'id_programa'
            ],
            'nombre',
            'referente',

            ['class' => 'yii\grid\ActionColumn'],
        ],
    ]); ?>

</div>
