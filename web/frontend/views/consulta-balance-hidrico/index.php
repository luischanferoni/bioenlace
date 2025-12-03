<?php

use yii\helpers\Html;
use yii\grid\GridView;
use yii\grid\ActionColumn;
/* @var $this yii\web\View */
/* @var $dataProvider yii\data\ActiveDataProvider */

$this->title = sprintf('Consulta %s - Balance Hídrico', $id_consulta);
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="consulta-balancehidrico-index">

    <div class="card">
        <div class="card-header bg-soft-info">
            <h1><?= Html::encode($this->title) ?></h1>
        </div>
        <div class="card-body">
            <p>
            <?= Html::a('Crear', 
                    ['create-crud', 'id_consulta'=>$id_consulta], 
                    ['class' => 'btn btn-success']) ?>
            </p>
    
    <?= GridView::widget([
        'dataProvider' => $dataProvider,
        'columns' => [
            ['class' => 'yii\grid\SerialColumn'],

            //'id',
            //'id_internacion',
            'fecha',
            'tipo_registro',
            //'cod_ingreso',
            //'cod_egreso',
            [
            'label' => 'Cod Registro',
            'value' => function ($model) {
                    return $model->getCodigoRegistroDescription();
                }
            ],
            'hora_inicio',
            'hora_fin',
            'cantidad',

            ['class' => 'yii\grid\ActionColumn',
                'visibleButtons' => [
                    'view' => false,
                    'update' => false,
                    'delete' => false]
                ],
        ],
    ]); ?>
        </div>
    </div>
</div>
