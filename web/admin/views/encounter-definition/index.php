<?php

use yii\helpers\Html;
use yii\grid\GridView;
use common\models\Clinical\EncounterDefinition;
use kartik\select2\Select2;
use yii\helpers\ArrayHelper;
use common\models\Servicio;

/* @var $this yii\web\View */
/* @var $dataProvider yii\data\ActiveDataProvider */
/* @var $searchModel common\models\busquedas\EncounterDefinitionBusqueda */

$this->title = 'Definiciones de encounter';
$this->params['breadcrumbs'][] = $this->title;
?>

<div class="card">
    <div class="card-header d-flex justify-content-between">
        <div class="col-8">
            <h4 class="card-title"><?= Html::encode($this->title) ?></h4>
        </div>
        <div class="col-2">
            <?= Html::a('Nuevo', ['create'], ['class' => 'btn btn-success float-end']) ?>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive mt-4 border rounded">
            <?= GridView::widget([
                'dataProvider' => $dataProvider,
                'filterModel' => $searchModel,
                'columns' => [
                    ['class' => 'yii\grid\SerialColumn'],
                    [
                        'attribute' => 'service_id',
                        'label' => 'Servicio',
                        'format' => 'raw',
                        'value' => static function ($data) {
                            return $data->servicio->nombre ?? $data->service_id;
                        },
                        'filter' => Select2::widget([
                            'model' => $searchModel,
                            'attribute' => 'service_id',
                            'data' => ArrayHelper::map(Servicio::find()->all(), 'id_servicio', 'nombre'),
                            'theme' => 'default',
                            'hideSearch' => false,
                            'options' => [
                                'placeholder' => 'Seleccione un servicio...',
                            ],
                        ]),
                    ],
                    [
                        'attribute' => 'encounter_class',
                        'label' => 'Clase',
                        'value' => static function ($data) {
                            return EncounterDefinition::ENCOUNTER_CLASS[$data->encounter_class] ?? $data->encounter_class;
                        },
                        'filter' => Select2::widget([
                            'model' => $searchModel,
                            'attribute' => 'encounter_class',
                            'data' => EncounterDefinition::ENCOUNTER_CLASS,
                            'theme' => 'default',
                            'hideSearch' => false,
                            'options' => [
                                'placeholder' => 'Seleccione uno...',
                            ],
                        ]),
                    ],
                    [
                        'attribute' => 'deleted_at',
                        'label' => 'Estado',
                        'value' => static function ($data) {
                            return $data->deleted_at === null ? 'ACTIVO' : 'INACTIVO';
                        },
                    ],
                    [
                        'class' => 'yii\grid\ActionColumn',
                        'template' => '{update}',
                    ],
                ],
            ]); ?>
        </div>
    </div>
</div>
