<?php

use yii\helpers\Html;
use yii\grid\GridView;

/* @var $this yii\web\View */
/* @var $searchModel common\models\BarriosSearch */
/* @var $dataProvider yii\data\ActiveDataProvider */

$this->title = 'Barrios';
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="barrios-index">
    <div class="box">
        <div class="box-header">
            <h1><?= Html::encode($this->title) ?></h1>
            <?php // echo $this->render('_search', ['model' => $searchModel]); ?>

            <p>
                <?= Html::a('Nuevo Barrio', ['create'], ['class' => 'btn btn-success']) ?>
            </p>
        </div>
        <div class="box-body">
            <?= GridView::widget([
                'dataProvider' => $dataProvider,
                'filterModel' => $searchModel,
                'columns' => [
                    //['class' => 'yii\grid\SerialColumn'],

                    'nombre',
                    [
                        'label' => 'Ubicacion',
                        'value' => function ($data) {
                            return $data->localidad->nombre; //.' | '.$data->localidad->departamento->nombre
                        },
                    ],

                    ['class' => 'yii\grid\ActionColumn', 'template' => '{view} {update}'],
                ],
            ]); ?>
        </div>
    </div>
</div>
