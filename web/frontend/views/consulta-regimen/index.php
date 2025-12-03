<?php

use yii\helpers\Html;
use yii\grid\GridView;

/* @var $this yii\web\View */
/* @var $dataProvider yii\data\ActiveDataProvider */

$this->title = sprintf('Consulta %s - Regimen', $id_consulta);
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="consulta-regimen-index">

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

            [
              'label' => 'Concepto',
              'value' => function ($model) {
                 return $model->getConceptTerm();
              }
            ],
            'indicaciones',

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