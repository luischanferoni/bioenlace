<?php

use yii\helpers\Html;
use yii\grid\GridView;
use yii\helpers\ArrayHelper;
use common\models\InternacionMedicamento;
use common\models\Rrhh_efector;

/* @var $this yii\web\View */
/* @var $searchModel common\models\busquedas\InternacionMedicamento */
/* @var $dataProvider yii\data\ActiveDataProvider */

$this->title = 'Listado de Suministros de medicamento realizados';
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="suministro-medicamento-index">

    <h1><?= Html::encode($this->title) ?></h1>

    <p>
        <?= Html::a('Registrar Suministro', ['create', 'idi' => $id_internacion], ['class' => 'btn btn-success']) ?>
    </p>
    <?= Html::a('Volver', ['internacion/view', 'id' => $id_internacion], ['class' => 'btn btn-primary']) ?>

    <?php // echo $this->render('_search', ['model' => $searchModel]); ?>

    <?= GridView::widget([
        'dataProvider' => $dataProvider,
        'filterModel' => $searchModel,
        'columns' => [
            ['class' => 'yii\grid\SerialColumn'],

            //'id',
            [
                'attribute' => 'id_rrhh',
                'label' => 'Medicamento', 
                'value' => function ($data) {
                   
                    return $data->internacionMedicamento? $data->internacionMedicamento->medicamentoSnomed->term : 'NO Definido';
                }               
                
            ],
            'fecha',
            'hora',            
            [
                'attribute' => 'id_rrhh',
                'label' => 'RRHH', 
                'value' => function ($data) {
                   
                    return $data->rrhhSuministra? $data->rrhhSuministra->rrhh->idPersona->nombreCompleto : 'NO';
                }               
                
            ],
            'observacion',          

            ['class' => 'yii\grid\ActionColumn'],
        ],
    ]); ?>


</div>
