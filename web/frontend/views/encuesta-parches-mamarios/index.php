<?php

use common\models\User;
use yii\helpers\Html;
use yii\grid\GridView;
use kartik\export\ExportMenu;
use kartik\daterange\DateRangePicker;
use yii\widgets\Pjax;


/* @var $this yii\web\View */
/* @var $dataProvider yii\data\ActiveDataProvider */

$this->title = 'Encuesta Parches Mamarios';
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="encuesta-parches-mamarios-index">
<div class="card">
    <div class="card-header bg-soft-info">
    <h1><?= Html::encode($this->title) ?></h1>
    </div>

    <div class="card-body">
            <div id="datatable_wrapper" class="dataTables_wrapper dt-bootstrap5 no-footer">
                <div class="table-responsive my-3">
                <?php Pjax::begin(); ?>

                <?php
                $gridColumns = [
                    [
                        'label' => 'DNI',
                        'attribute' => 'dni',
                        'value' => function ($data) {
                            return isset($data->persona) ? $data->persona->documento : '';
                        }
                    ],            
                    [
                        'label' => 'Apellido y Nombre',
                        'attribute' => 'apellido',
                        'value' => function ($data) {
                            return isset($data->persona) ? $data->persona->apellido.', '.$data->persona->nombre: '';
                        }
                    ],
                    [
                        'label' => 'Fecha de carga',
                        'attribute' => 'created_at',
                        'filter' => DateRangePicker::widget([
                            'model' => $searchModel,
                            'attribute' => 'rango_fechas',
                            'convertFormat' => true,
                            'pluginOptions' => [
                                'locale' => [
                                    'format' => 'Y-m-d'
                                ],
                            ],                
                        ]),
                    ],
                    [
                        'label' => 'Efector',
                        'attribute' => 'efector',
                        'value' => function ($data) {
                            if($data->created_at < '2024-03-28 00:00:00') {
                                return $data->efector->nombre;
                            } else {
                                return isset($data->operador) ? $data->operador->efector->nombre:$data->efector->nombre;
                            }
                        }
                    ],
                    [
                        'attribute' => 'resultado',
                        'filter' => Html::activeDropDownList($searchModel, 'resultado', 
                                                        ['todos' => 'Todos', 'No Significativa' => 'No Significativa', 'Significativa' => 'Significativa', 'No concluyente' => 'No concluyente'],
                                                        ['class' => 'form-control',])
                    ],
                    [
                        'attribute' => 'resultado_indicado',
                        'filter' => Html::activeDropDownList($searchModel, 'resultado_indicado', 
                                                        ['todos' => 'Todos','No Significativa' => 'No Significativa', 'Significativa' => 'Significativa', 'No concluyente' => 'No concluyente'],
                                                        ['class' => 'form-control',])
                    ]
                ];

                $todasColumns = [
                    [
                        'label' => 'DNI',
                        'attribute' => 'dni',
                        'value' => function ($data) {
                            return isset($data->persona) ? $data->persona->documento : '';
                        }
                    ],            
                    [
                        'label' => 'Apellido y Nombre',
                        'attribute' => 'apellido',
                        'value' => function ($data) {
                            return isset($data->persona) ? $data->persona->apellido.', '.$data->persona->nombre: '';
                        }
                    ],
                    'fecha_prueba',
                    [
                        'label' => 'Fecha de carga',
                        'attribute' => 'created_at',
                    ],
                    [
                        'label' => 'Efector',
                        'attribute' => 'efector',
                        'value' => function ($data) {
                            if($data->created_at < '2024-03-28 00:00:00') {
                                return $data->efector->nombre;
                            } else {
                                return isset($data->operador) ? $data->operador->efector->nombre:$data->efector->nombre;
                            }
                            
                        }
                    ],        
                    [
                        'label' => 'Operador',
                        'attribute' => 'id_operador',
                        'value' => function ($data) {
                           
                                return isset($data->operador) ? $data->operador->persona->getNombreCompleto(common\models\Persona::FORMATO_NOMBRE_A_OA_N_ON): '';
                            
                        }
                    ],
                    [
                        'label' => 'Cargado por',
                        'attribute' => 'created_by',
                        'value' => function ($data) {
                           
                             if(isset($data->created_by_id)){
                                $dataEntry = common\models\Persona::findOne(['id_user' => $data->created_by_id]);                                
                             }
                             return isset($dataEntry)? $dataEntry->getNombreCompleto(common\models\Persona::FORMATO_NOMBRE_A_OA_N_ON) : '-'; 
                            
                        }
                    ],
                    'numero_serie',
                    'antecedente_cancer_mama',
                    'antecedente_cirugia_mamaria',
                    'actualmente_amamantando',
                    'sintomas_enfermedad_mamaria',
                    'edad_primer_periodo',
                    'tiene_hijos',
                    'edad_primer_parto',
                    'paso_menospausia',
                    'edad_menospausia',
                    'terapia_remplazo_hormonal',
                    'senos_densos',
                    'biopsia_mamaria',
                    'fecha_biopsia',
                    'resultado_biopsia',
                    'antecedente_familiar_cancer_mamario_ovarico',
                    'consume_alcohol',
                    'consume_tabaco',
                    'mamografia',
                    'fecha_ultima_mamografia',
                    'prueba_adicional',
                    'prueba_adicional_tipo',
                    'a_izquierdo',
                    'a_derecho',
                    'a_diferencia',
                    'b_izquierdo',
                    'b_derecho',
                    'b_diferencia',
                    'c_izquierdo',
                    'c_derecho',
                    'c_diferencia',
                    'observaciones',
                    [
                        'attribute' => 'resultado',
                        'filter' => Html::activeDropDownList($searchModel, 'resultado', 
                                                        ['No Significativa' => 'No Significativa', 'Significativa' => 'Significativa', 'No concluyente' => 'No concluyente'],
                                                        ['class' => 'form-control',])
                    ],
                    [
                        'attribute' => 'resultado_indicado',
                        'filter' => Html::activeDropDownList($searchModel, 'resultado_indicado', 
                                                        ['No Significativa' => 'No Significativa', 'Significativa' => 'Significativa', 'No concluyente' => 'No concluyente'],
                                                        ['class' => 'form-control',])
                    ]  
                ];    
                ?>
            <?php $customDropdown = [
                'options' => ['tag' => false], 
                'linkOptions' => ['class' => 'dropdown-item']
            ];?>
            <?= ExportMenu::widget([
                    'dataProvider' => $dataProvider,
                    'batchSize' => 20,
                    'columnSelectorOptions'=>[
                        'label' => 'Columnas',
                    ],
                    'columns' => $todasColumns,
                    'clearBuffers' => true, //optional
                    'dropdownOptions' => [
                        'label' => 'Exportar',
                        'class' => 'badge bg-secondary text-light'
                    ],
                    'exportConfig' => [ // set styling for your custom dropdown list items
                        ExportMenu::FORMAT_CSV => false,
                        ExportMenu::FORMAT_TEXT => false,
                        ExportMenu::FORMAT_HTML => false,
                        ExportMenu::FORMAT_PDF => false,
                        ExportMenu::FORMAT_EXCEL =>false,
                        ExportMenu::FORMAT_EXCEL_X => $customDropdown,
                    ],
                ]);
            ?>
            
            <?= \kartik\grid\GridView::widget([
                'dataProvider' => $dataProvider,
                'filterModel' => $searchModel,
                'class'=> 'table mb-0 dataTable no-footer" id="datatable" data-toggle="data-table" aria-describedby="datatable_info"',
                'columns' => $gridColumns,
            ]); ?>
 <?php Pjax::end(); ?>
 </div>
                </div>
            </div>
</div>
