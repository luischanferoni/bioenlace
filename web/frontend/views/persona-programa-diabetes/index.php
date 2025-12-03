<?php

use yii\helpers\Html;
use yii\grid\GridView;

/* @var $this yii\web\View */
/* @var $dataProvider yii\data\ActiveDataProvider */

$this->title = 'Persona Programa Diabetes';
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="persona-programa-diabetes-index">

    <h1><?= Html::encode($this->title) ?></h1>

    <p>
        <?= Html::a('Create Persona Programa Diabetes', ['create'], ['class' => 'btn btn-success']) ?>
    </p>


    <?= GridView::widget([
        'dataProvider' => $dataProvider,
        'columns' => [
            ['class' => 'yii\grid\SerialColumn'],

            'id',
            'id_persona_programa',
            'tipo_diabetes',
            'incluir_salud',
            'id_persona_autorizada',
            //'parentesco_persona_autorizada',
            //'ins_lenta_nph',
            //'ins_lenta_lantus',
            //'ins_rapida_novorapid',
            //'metformina_500',
            //'metformina_850',
            //'glibenclamida',
            //'tiras',
            //'monitor',
            //'lanceta',
            //'id_rrhh_efector',
            //'hba1c',
            //'glucemia',

            ['class' => 'yii\grid\ActionColumn'],
        ],
    ]); ?>


</div>
