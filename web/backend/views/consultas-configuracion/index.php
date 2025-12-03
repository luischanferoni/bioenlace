<?php

use yii\helpers\Html;
use yii\grid\GridView;
use common\models\ConsultasConfiguracion;
use kartik\select2\Select2;
use yii\helpers\ArrayHelper;
use common\models\Servicio;


/* @var $this yii\web\View */
/* @var $dataProvider yii\data\ActiveDataProvider */

$this->title = 'Consultas Configuraciones';
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
                        'attribute' => 'id_servicio',
                        'label' => 'Servicio',
                        'format' => 'raw',
                        'value' => function ($data) {
                            return $data->servicio->nombre;
                        },
                        'filter' => Select2::widget([
                                'model' => $searchModel,                            
                                'attribute' => 'id_servicio',                            
                                'data' => ArrayHelper::map(Servicio::find()->all(), 'id_servicio', 'nombre'),                                
                                'theme' => 'default',                            
                                'hideSearch' => false,                            
                                'options' => [                            
                                    'placeholder' => 'Seleccione un servicio...',                            
                                ]                            
                            ])
                    ],
                    [
                        'attribute' => 'encounter_class',
                        'label' => 'Encounter Class',
                        'value' => function($data) {
                            return ConsultasConfiguracion::ENCOUNTER_CLASS[$data->encounter_class];
                        },
                        'filter' => Select2::widget([
                                'model' => $searchModel,                            
                                'attribute' => 'encounter_class',                            
                                'data' => ConsultasConfiguracion::ENCOUNTER_CLASS,                                
                                'theme' => 'default',                            
                                'hideSearch' => false,                            
                                'options' => [                            
                                    'placeholder' => 'Seleccione uno...',                            
                                ]                            
                            ])
                    ],
                    [
                        'attribute' => 'deleted_at',
                        'label' => 'Estado',
                        'value' => function($data) {
                            if(is_null($data->deleted_at)){
                                $estado = 'ACTIVO';
                            } else {
                                $estado = 'INACTIVO';
                            }
                            return $estado;
                        }
                    ],
                    [
                        'class' => 'yii\grid\ActionColumn',
                        'template'=>'{update}'
                    ], 
                ],
            ]); ?>
        </div>
    </div>
</div>
