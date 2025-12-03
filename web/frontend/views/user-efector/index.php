<?php

use yii\helpers\Html;
use yii\grid\GridView;

/* @var $this yii\web\View */
/* @var $dataProvider yii\data\ActiveDataProvider */

$this->title = 'Asignar Efectores';
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="user-efector-index">

    <h1><?= Html::encode($this->title) ?></h1>

    <p>
        <?= Html::a('Nueva Asignación', ['create'], ['class' => 'btn btn-success']) ?>
    </p>

    <?= GridView::widget([
        'dataProvider' => $dataProvider,
        'columns' => [
            ['class' => 'yii\grid\SerialColumn'],
            [
            'label' => 'Usuario',
            'attribute' => 'id_user',
            'value' => function ($data) {
               
                return $data->user->username;
            }
            ],
                    [
            'label' => 'Efector',
            'attribute' => 'id_efector',
            'value' => function ($data) {
               
                return $data->efector->nombre;
            }
            ],
            
            ['class' => 'yii\grid\ActionColumn'],
        ],
    ]); ?>

</div>
