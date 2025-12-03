<?php

use yii\helpers\Html;
use yii\grid\GridView;

/* @var $this yii\web\View */
/* @var $dataProvider yii\data\ActiveDataProvider */

$this->title = 'Persona Programas';
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="persona-programa-index">

    <h1><?= Html::encode($this->title) ?></h1>

    <p>
        <?= Html::a('Create Persona Programa', ['create'], ['class' => 'btn btn-success']) ?>
    </p>


    <?= GridView::widget([
        'dataProvider' => $dataProvider,
        'columns' => [
            ['class' => 'yii\grid\SerialColumn'],

            'id',
            'id_persona',
            'id_programa',
            'clave_beneficiario',
            'activo',
            //'fecha',
            //'fecha_baja',
            //'motivo_baja',
            //'tipo_empadronamiento',
            //'id_rrhh_efector',

            ['class' => 'yii\grid\ActionColumn'],
        ],
    ]); ?>


</div>
