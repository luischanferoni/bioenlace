<?php
use yii\helpers\Html;
use yii\grid\GridView;
use yii\helpers\ArrayHelper;
use common\models\Persona;

$this->title = 'Internaciones de '. $persona->getNombreCompleto(Persona::FORMATO_NOMBRE_A_OA_N_ON);
$this->params['breadcrumbs'][] = $this->title;
?>
    <div class='card'>
        <div class="card-header bg-soft-info">
            <h4><?= Html::encode($this->title) ?></h4>
        </div>
        <div class='card-body'>       
            <div class="row">
                <?= GridView::widget([
                    'dataProvider' => $dataProvider,
                    'options' => ['class' => 'table-responsive'],
                    'tableOptions' => ['class' => 'table table-striped table-hover table-bordered rounded'],
                    'columns' => [
                        //['class' => 'yii\grid\SerialColumn'],

                        //'id', 
                        [
                            'label' => 'Cama',
                            'attribute' => 'id_cama',
                            'value' => function ($data) {                   
                                return $data->cama? $data->cama->nro_cama: 'No definida';
                            }
                        ],   
                        [
                            'label' => 'Sala',
                            'attribute' => 'id_sala',
                            'value' => function ($data) {                   
                                return $data->cama? $data->cama->sala->nro_sala. '-'.$data->cama->sala->descripcion: 'No definida';
                            }
                        ], 
                        [
                            'label' => 'Piso',
                            'attribute' => 'id_piso',
                            'value' => function ($data) {                   
                                return $data->cama? $data->cama->sala->piso->nro_piso: 'No definido';
                            }
                        ], 
                        [
                            'label' => 'Efector',
                            'attribute' => 'id_efector',
                            'value' => function ($data) {                   
                                return $data->cama? $data->cama->sala->piso->efector->nombre: 'No definido';
                            }
                        ],   
                        [
                            'label' => 'Ingreso',
                            'attribute' => 'fecha_inicio',
                            'value' => function ($data) {                   
                                return $data->fecha_inicio? $data->fecha_inicio.' '. $data->hora_inicio: 'No definido';
                            }
                        ],                    
                        'tipo_ingreso',
                        [
                            'label' => 'Egreso',
                            'attribute' => 'fecha_fin',
                            'value' => function ($data) {                   
                                return $data->fecha_fin? $data->fecha_fin.' '. $data->hora_fin: 'No definido';
                            }
                        ], 
                        
                        [
                            'label' => 'Tipo de alta',
                            'attribute' => 'id_tipo_alta',
                            'value' => function ($data) {                   
                                return $data->tipoAlta? $data->tipoAlta->tipo_alta: 'No definida';
                            }
                        ],

                        //['class' => 'yii\grid\ActionColumn'],
                    ],
                ]); ?>
            </div>
        </div>
    </div>