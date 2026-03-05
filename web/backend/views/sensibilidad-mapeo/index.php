<?php

use yii\helpers\Html;
use yii\grid\GridView;

/* @var $this yii\web\View */
/* @var $searchModel common\models\busquedas\SensibilidadMapeoSnomedBusqueda */
/* @var $dataProvider yii\data\ActiveDataProvider */

$this->title = 'Mapeo SNOMED → sensibilidad';
$this->params['breadcrumbs'][] = ['label' => 'Resumen IA', 'url' => ['#']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="sensibilidad-mapeo-index">

    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <div class="header-title d-flex align-items-self justify-content-between">
                    <h2 class="card-title mt-1"><?= Html::encode($this->title) ?></h2>
                    <?= Html::a('Nuevo mapeo', ['create'], ['class' => 'btn btn-success']) ?>
                    <?= Html::a('Categorías', ['sensibilidad-categoria/index'], ['class' => 'btn btn-outline-secondary']) ?>
                </div>
            </div>
            <div class="card-body">
                <p class="text-muted small">Asigne códigos SNOMED (de hallazgos, medicamentos, motivos, problemas, procedimientos, síntomas, situación) a una categoría de sensibilidad.</p>

                <?= GridView::widget([
                    'dataProvider' => $dataProvider,
                    'filterModel' => $searchModel,
                    'columns' => [
                        'id',
                        [
                            'attribute' => 'tabla_snomed',
                            'value' => function ($model) {
                                return \common\models\SensibilidadMapeoSnomed::TABLAS[$model->tabla_snomed] ?? $model->tabla_snomed;
                            },
                            'filter' => \common\models\SensibilidadMapeoSnomed::TABLAS,
                        ],
                        'codigo',
                        [
                            'attribute' => 'Término',
                            'format' => 'raw',
                            'value' => function ($model) {
                                return Html::encode($model->getTerminoSnomed());
                            },
                        ],
                        [
                            'attribute' => 'id_categoria',
                            'value' => function ($model) {
                                return $model->categoria ? $model->categoria->nombre : '';
                            },
                            'filter' => \yii\helpers\ArrayHelper::map(\common\models\SensibilidadCategoria::find()->orderBy('nombre')->all(), 'id', 'nombre'),
                        ],
                        ['class' => 'yii\grid\ActionColumn'],
                    ],
                ]); ?>
            </div>
        </div>
    </div>
</div>
