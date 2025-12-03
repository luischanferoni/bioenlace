<?php

use yii\helpers\Html;
use yii\grid\GridView;

/* @var $this yii\web\View */
/* @var $searchModel common\models\RrhhBusqueda */
/* @var $dataProvider yii\data\ActiveDataProvider */

$this->title = 'Rrhhs';
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="rrhh-index">

    <h1><?= Html::encode($this->title) ?></h1>
    <?php // echo $this->render('_search', ['model' => $searchModel]); ?>

    <p>
        <?= Html::a('Nuevo Rrhh', ['create'], ['class' => 'btn btn-success']) ?>
    </p>

    <?= GridView::widget([
        'dataProvider' => $dataProvider,
        'filterModel' => $searchModel,
        'columns' => [
            ['class' => 'yii\grid\SerialColumn'],

//            'id_rr_hh',
            
            [
            'label' => 'Apellido',
            'attribute' => 'apellido',
            'value' => 'persona.apellido'
            ],
            [
            'label' => 'Nombre',
            'attribute' => 'nombre',
            'value' => 'persona.nombre'
            ],
            [
            'label' => 'Profesion',
            'attribute' => 'profesion',
            'value' => 'profesion.nombre'
            ],
            [
            'label' => 'Especialidad',
            'attribute' => 'especialidad',
            'value' => 'idEspecialidad.nombre'
            ],

            ['class' => 'yii\grid\ActionColumn'],
        ],
    ]); ?>

</div>
