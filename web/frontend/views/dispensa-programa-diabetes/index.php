<?php

use yii\helpers\Html;
use yii\grid\GridView;

/* @var $this yii\web\View */
/* @var $dataProvider yii\data\ActiveDataProvider */

$this->title = 'Dispensa Programa Diabetes';
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="dispensa-programa-diabetes-index">

    <h1><?= Html::encode($this->title) ?></h1>

    <p>
        <?= Html::a('Create Dispensa Programa Diabetes', ['create'], ['class' => 'btn btn-success']) ?>
    </p>


    <?= GridView::widget([
        'dataProvider' => $dataProvider,
        'columns' => [
            ['class' => 'yii\grid\SerialColumn'],

            'id',
            'id_persona_programa_diabetes',
            'id_persona_retira',
            'fecha_retiro',
            'ins_lenta_nph',
            //'ins_lenta_lantus',
            //'ins_rapida_novorapid',
            //'metformina_500',
            //'metformina_850',
            //'glibenclamida',
            //'tiras',
            //'monitor',
            //'lanceta',
            //'id_rrhh_efector',

            ['class' => 'yii\grid\ActionColumn'],
        ],
    ]); ?>


</div>
