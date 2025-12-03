<?php

use yii\helpers\Html;
use yii\grid\GridView;
use yii\widgets\Pjax;

use webvimark\modules\UserManagement\models\User;

use common\models\Persona;
/* @var $this yii\web\View */
/* @var $searchModel common\models\busquedas\GuardiaBusqueda */
/* @var $dataProvider yii\data\ActiveDataProvider */

$this->title = 'Guardias';
$this->params['breadcrumbs'][] = $this->title;

$this->registerJsFile(
    '@web/js/consultas.js',
    ['depends' => [\yii\web\JqueryAsset::class]]
);
?>
<div class="guardia-index">
<div class="card">
    <div class="card-header bg-soft-info">
        <h3><?= Html::encode($this->title) ?></h3>        
    </div>
    <div class="card-body">
        <p style="display: flex;justify-content: end;">
            <?= Html::a('Nuevo Ingreso', ['create'], ['class' => 'btn btn-success']) ?>
        </p>
        <div class="custom-table-effect table-responsive  border rounded">
            <div id="datatable_wrapper" class="dataTables_wrapper dt-bootstrap5 no-footer">
                <div class="table-responsive my-3">
                    <?php Pjax::begin(); ?>
                    <?php // echo $this->render('_search', ['model' => $searchModel]); ?>

                    <?= GridView::widget([
                        'dataProvider' => $dataProvider,
                        //'filterModel' => $searchModel,
                        'class'=> 'table-responsive my-3',
                        'columns' => [
                            //['class' => 'yii\grid\SerialColumn'],

                            'estado',
                            [
                                'label' => 'Paciente',
                                'attribute' => 'id_persona',
                                'format' => 'raw',
                                'value' => function ($data) {                   
                                    if($data->paciente) {
                                        $nombreAcciones = '<div class="d-flex align-items-center"><div class="media-support-info">';
                                        $nombreAcciones .= "<h5 class='iq-sub-label'>".$data->paciente->getNombreCompleto(Persona::FORMATO_NOMBRE_A_OA_N_ON)."</h5>".
                                            "<div class='d-grid gap-card grid-cols-2 mt-3'>";
                                            if (User::hasRole("Medico") || User::hasRole("enfermeria")) {
                                                $nombreAcciones .= Html::a('Historia Clínica', 
                                                        ['paciente/historia/'.$data->id_persona],
                                                        ['class' => 'btn btn-outline-info me-2']
                                                );
                                            }

                                            $nombreAcciones .= Html::a('Finalizar', 
                                                    ['guardia/finalizar/'.$data->id],
                                                    ['class' => 'btn btn-outline-dark me-2']
                                            );
                             
                                        return $nombreAcciones .= "</div></div></div>";
                           
                                    } else {
                                        return 'No definida';
                                    }
                                }
                            ],
                            [
                                'label' => 'Fecha',
                                'attribute'=> 'fecha',
                                'value' => function ($data) {
                                    return Yii::$app->formatter->asDate($data->fecha, 'dd-MM-yyyy');
                                }
                            ],
                            'hora',
                            
                            [
                                'label' => 'Fecha Nacimiento',
                                'attribute' => 'id_persona',
                                'value' => function ($data) {                   
                                    return $data->paciente? Yii::$app->formatter->asDate($data->paciente->fecha_nacimiento, 'dd-MM-yyyy'): 'No definida';
                                }
                            ],
                            [
                                'label' => 'DNI',
                                'attribute' => 'id_persona',
                                'value' => function ($data) {                   
                                    return $data->paciente? $data->paciente->documento: 'No definida';
                                }
                            ],
                            [
                                'label' => 'Domicilio',
                                'attribute' => 'id_persona',
                                'value' => function ($data) {                   
                                    return ( $data->paciente->getDomicilioActivo())? $data->paciente->domicilioActivo->domicilioCompleto: 'No definida';
                                }
                            ],            
                            
                            //'id_rrhh_asignado',
                            //'created_at',
                            //'updated_at',
                            //'deleted_at',
                            //'created_by',
                            //'updated_by',
                            //'deleted_by',
                            //'cobertura',
                            //'situacion_al_ingresar:ntext',
                            //'id_efector_derivacion',
                            //'condiciones_derivacion:ntext',
                            //'notificar_internacion_id_efector',

                            //['class' => 'yii\grid\ActionColumn'],
                        ],
                    ]); ?>

                    <?php Pjax::end(); ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
