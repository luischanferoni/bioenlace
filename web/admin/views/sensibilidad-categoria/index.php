<?php

use yii\helpers\Html;
use yii\grid\GridView;

/* @var $this yii\web\View */
/* @var $dataProvider yii\data\ActiveDataProvider */

$this->title = 'Categorías de sensibilidad';
$this->params['breadcrumbs'][] = ['label' => 'Resumen IA', 'url' => ['#']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="sensibilidad-categoria-index">

    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <div class="header-title d-flex align-items-self justify-content-between">
                    <h2 class="card-title mt-1"><?= Html::encode($this->title) ?></h2>
                    <?= Html::a('Nueva categoría', ['create'], ['class' => 'btn btn-success']) ?>
                </div>
            </div>
            <div class="card-body">
                <p class="text-muted small">Las categorías agrupan códigos SNOMED. Por categoría se define una regla: acción (generalizar/ocultar) y lista de servicios que la reciben; el resto ve el dato completo.</p>
                <?= GridView::widget([
                    'dataProvider' => $dataProvider,
                    'columns' => [
                        'id',
                        'nombre',
                        [
                            'attribute' => 'descripcion',
                            'format' => 'ntext',
                            'value' => function ($model) {
                                return $model->descripcion ? \yii\helpers\StringHelper::truncate($model->descripcion, 60) : '';
                            },
                        ],
                        ['class' => 'yii\grid\ActionColumn'],
                    ],
                ]); ?>
            </div>
        </div>
    </div>
</div>
