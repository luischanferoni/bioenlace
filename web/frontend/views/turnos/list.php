<?php

use yii\helpers\Html;
use yii\grid\GridView;
use yii\widgets\Pjax;
use common\models\Persona;
use common\models\RrhhEfector;
use common\models\Turno;
use kartik\daterange\DateRangePicker;
use kartik\select2\Select2;
use yii\helpers\ArrayHelper;
use kartik\export\ExportMenu;
use common\models\Servicio;
/* @var $this yii\web\View */
/* @var $searchModel common\models\busquedas\GuardiaBusqueda */
/* @var $dataProvider yii\data\ActiveDataProvider */

$this->title = 'Historial de Turnos';
$this->params['breadcrumbs'][] = $this->title;
$estados = array(Turno::ESTADO_PENDIENTE => 'bg-soft-warning p-2 text-warning', Turno::ESTADO_CANCELADO => 'bg-secondary', Turno::ESTADO_EN_ATENCION => 'bg-success', Turno::ESTADO_ATENDIDO => 'bg-info', Turno::ESTADO_SIN_ATENDER => 'bg-danger');
?>
<div class="turnos-listado">
<div class="card">
    <div class="card-header bg-soft-info">
        <h3><?= Html::encode($this->title) ?></h3>        
    </div>

    <div class="card-body">
            <div id="datatable_wrapper" class="dataTables_wrapper dt-bootstrap5 no-footer">
                <div class="table-responsive my-3">
                    <?php Pjax::begin(); ?>
                    <?php // echo $this->render('_search', ['model' => $searchModel]); ?>
                    <?php $gridColumns = [
                                        [
                                            'label' => 'Fecha',
                                            'attribute'=> 'fecha',
                                            'format' => 'text',
                                            'filter' => '<div class="drp-container input-group"><span class="input-group-addon"></span>' .
                                                DateRangePicker::widget([
                                                    'name'  => 'TurnoBusqueda[fecha]',
                                                    'options' => ['class' => 'form-control', 'placeholder'=>'Filtrar por fecha'],
                                                    'pluginOptions' => [
                                                        'locale' => [
                                                            'separator' => ' - ',
                                                        ],
                                                        'opens' => 'right'
                                                    ]
                                                ]) . '</div>',
                                            'value' => function ($data) {
                                                return Yii::$app->formatter->asDate($data->fecha, 'dd/MM/yyyy');
                                            },
                                        ],
                                        [
                                            'attribute'=>'hora',
                                            'filter'=>false,
                                        
                                        ],
                                        [
                                            'label' => 'Paciente',
                                            'attribute' => 'id_persona',
                                            'format' => 'raw',
                                            'filter'=>false,
                                            'value' => function ($data) {                   
                                                return $data->id_persona? $data->persona->getNombreCompleto(Persona::FORMATO_NOMBRE_A_OA_N_ON): 'No definida';
                                            }
                                        ],
                                        [
                                            'label' => 'DNI',
                                            'attribute' => 'id_persona',
                                            'format' => 'raw',
                                            'filter'=>false,
                                            'value' => function ($data) {                   
                                                return $data->id_persona? $data->persona->documento: 'No definida';
                                            }
                                        ],
                                        [
                                            'label' => 'Profesional',
                                            'attribute' => 'id_rrhh_servicio_asignado',
                                            'format' => 'raw',
                                            //'filter'=>false,
                                            'value' => function ($data) {  
                                                return $data->id_rrhh_servicio_asignado? $data->rrhhServicioAsignado->rrhhEfector->persona->getNombreCompleto(Persona::FORMATO_NOMBRE_A_OA_N_ON): 
                                                        'SIN ESPECIFICAR';
                                            },
                                            'filter' => Select2::widget([

                                                'model' => $turnos,
                                            
                                                'attribute' => 'id_rrhh_servicio_asignado',
                                            
                                                'data' => ArrayHelper::map(RrhhEfector::obtenerMedicosPorEfector(Yii::$app->user->getIdEfector()), 'id', 'datos'),
                                                
                                                'theme' => 'default',
                                            
                                                'hideSearch' => false,
                                            
                                                'options' => [
                                            
                                                    'placeholder' => 'Seleccione un profesional...',
                                            
                                                ]
                                            
                                            ])
                                        ],
                                        [
                                            'label' => 'Estado',
                                            'attribute' => 'estado',
                                            'format' => 'raw',
                                            'value' => function ($data) use($estados) {   
                                                $ref = ($data->id_consulta_referencia != 0)?'<span class="badge bg-info">Referencia</span>':'';
                                                if($data->fecha < '2024-03-28' && $data->atendido == Turno::ATENDIDO_SI){

                                                    return $ref.' <span class="badge '.$estados[Turno::ESTADO_ATENDIDO].'">'.strtoupper(Turno::ESTADOS[Turno::ESTADO_ATENDIDO]).'</span>';

                                                }else{
                                                    return ($data->estado)? $ref.' <span class="badge '.$estados[$data->estado].'">'.strtoupper(Turno::ESTADOS[$data->estado]).'</span>': 'No definida';
                                                }               
                                               
                                            }
                                        ],
                                        [
                                            'attribute' => 'servicio.nombre',
                                            'label' => 'Servicio',
                                            /*'filter' => Html::activeDropDownList(
                                                $turnos,
                                                'id_servicio_asignado',
                                                ArrayHelper::map(Servicio::find()->all(), 'id_servicio', 'nombre'),
                                                [
                                                    'class' => 'select2',
                                                    'prompt' => '- Seleccione -'
                                                ]
                                            )*/
                                            'filter' => Select2::widget([

                                                'model' => $turnos,
                                            
                                                'attribute' => 'id_servicio_asignado',
                                            
                                                'data' => ArrayHelper::map(Servicio::find()->all(), 'id_servicio', 'nombre'),
                                                
                                                'theme' => 'default',
                                            
                                                'hideSearch' => false,
                                            
                                                'options' => [
                                            
                                                    'placeholder' => 'Seleccione un servicio...',
                                            
                                                ]
                                            
                                            ])
                                        ]          
                                    ]
                    ?>

                    <?= ExportMenu::widget([
                            'dataProvider' => $dataProvider,
                            'columns' => $gridColumns,
                            'clearBuffers' => true, //optional
                            'dropdownOptions' => [
                                'label' => 'Exportar',
                                'class' => 'badge bg-secondary text-light'
                            ],
                        ]);
                    ?>
                    <?= GridView::widget([
                            'dataProvider' => $dataProvider,
                            'filterModel' => $turnos,
                            'class'=> 'table mb-0 dataTable no-footer" id="datatable" data-toggle="data-table" aria-describedby="datatable_info"',
                            'columns' => $gridColumns,
                        ]); 
                    ?>

                    <?php Pjax::end(); ?>
                    </div>
                </div>
            </div>
    </div>
</div>