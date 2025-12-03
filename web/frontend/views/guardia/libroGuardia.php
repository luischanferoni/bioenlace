<?php

use yii\helpers\Html;
use yii\grid\GridView;
use yii\widgets\Pjax;
use common\models\Persona;
use kartik\daterange\DateRangePicker;
/* @var $this yii\web\View */
/* @var $searchModel common\models\busquedas\GuardiaBusqueda */
/* @var $dataProvider yii\data\ActiveDataProvider */

$this->title = 'Libro de Guardias';
$this->params['breadcrumbs'][] = $this->title;

$acciones = array(
    "Medico" => array(
        array ( "nombre" => "view",
                "svg" => '<svg height="100%" style="fill-rule:evenodd;clip-rule:evenodd;stroke-linejoin:round;stroke-miterlimit:2;" version="1.1" viewBox="0 0 32 32" width="100%" xml:space="preserve" xmlns="http://www.w3.org/2000/svg" xmlns:serif="http://www.serif.com/" xmlns:xlink="http://www.w3.org/1999/xlink"><path d="M25.912,5c-0,-0.796 -0.316,-1.559 -0.879,-2.121c-0.562,-0.563 -1.325,-0.879 -2.121,-0.879c-3.431,-0 -10.4,-0 -13.831,-0c-0.795,-0 -1.558,0.316 -2.121,0.879c-0.563,0.562 -0.879,1.325 -0.879,2.121l0,22c0,0.796 0.316,1.559 0.879,2.121c0.563,0.563 1.326,0.879 2.121,0.879c3.431,0 10.4,0 13.831,0c0.796,0 1.559,-0.316 2.121,-0.879c0.563,-0.562 0.879,-1.325 0.879,-2.121l-0,-22Zm-2,-0l-0,22c-0,0.265 -0.105,0.52 -0.293,0.707c-0.188,0.188 -0.442,0.293 -0.707,0.293l-13.831,-0c-0.265,0 -0.519,-0.105 -0.707,-0.293c-0.187,-0.187 -0.293,-0.442 -0.293,-0.707c0,-0 0,-22 0,-22c0,-0.265 0.106,-0.52 0.293,-0.707c0.188,-0.188 0.442,-0.293 0.707,-0.293l13.831,-0c0.265,-0 0.519,0.105 0.707,0.293c0.188,0.187 0.293,0.442 0.293,0.707Z"/><path d="M14.995,8l-0.998,0c-0.552,0 -1,0.448 -1,1c-0,0.552 0.448,1 1,1l1.002,-0l0.002,1.002c0.001,0.552 0.45,0.999 1.002,0.998c0.552,-0.001 0.999,-0.45 0.998,-1.002l-0.002,-0.998l0.998,0c0.551,0 1,-0.448 1,-1c-0,-0.552 -0.449,-1 -1,-1l-1.002,0l-0.003,-1.002c-0.001,-0.552 -0.45,-0.999 -1.002,-0.998c-0.552,0.001 -0.999,0.45 -0.998,1.002l0.003,0.998Z"/><path d="M14.992,17l6.016,-0c0.552,-0 1,-0.448 1,-1c-0,-0.552 -0.448,-1 -1,-1l-6.016,-0c-0.552,-0 -1,0.448 -1,1c0,0.552 0.448,1 1,1Z"/><path d="M14.992,21l6.016,0c0.552,0 1,-0.448 1,-1c-0,-0.552 -0.448,-1 -1,-1l-6.016,0c-0.552,0 -1,0.448 -1,1c0,0.552 0.448,1 1,1Z"/><path d="M14.985,24.994l6.015,-0c0.552,-0 1,-0.448 1,-1c-0,-0.552 -0.448,-1 -1,-1l-6.015,-0c-0.552,-0 -1,0.448 -1,1c-0,0.552 0.448,1 1,1Z"/><circle cx="10.998" cy="15.958" r="1"/><circle cx="10.998" cy="19.976" r="1"/><circle cx="10.998" cy="23.994" r="1"/></svg>',
                "title" => "Ver Detalles")
                ),
        
    "enfermeria" => array(     
        array ( "nombre" => "view",
                "svg" => '<svg height="100%" style="fill-rule:evenodd;clip-rule:evenodd;stroke-linejoin:round;stroke-miterlimit:2;" version="1.1" viewBox="0 0 32 32" width="100%" xml:space="preserve" xmlns="http://www.w3.org/2000/svg" xmlns:serif="http://www.serif.com/" xmlns:xlink="http://www.w3.org/1999/xlink"><path d="M25.912,5c-0,-0.796 -0.316,-1.559 -0.879,-2.121c-0.562,-0.563 -1.325,-0.879 -2.121,-0.879c-3.431,-0 -10.4,-0 -13.831,-0c-0.795,-0 -1.558,0.316 -2.121,0.879c-0.563,0.562 -0.879,1.325 -0.879,2.121l0,22c0,0.796 0.316,1.559 0.879,2.121c0.563,0.563 1.326,0.879 2.121,0.879c3.431,0 10.4,0 13.831,0c0.796,0 1.559,-0.316 2.121,-0.879c0.563,-0.562 0.879,-1.325 0.879,-2.121l-0,-22Zm-2,-0l-0,22c-0,0.265 -0.105,0.52 -0.293,0.707c-0.188,0.188 -0.442,0.293 -0.707,0.293l-13.831,-0c-0.265,0 -0.519,-0.105 -0.707,-0.293c-0.187,-0.187 -0.293,-0.442 -0.293,-0.707c0,-0 0,-22 0,-22c0,-0.265 0.106,-0.52 0.293,-0.707c0.188,-0.188 0.442,-0.293 0.707,-0.293l13.831,-0c0.265,-0 0.519,0.105 0.707,0.293c0.188,0.187 0.293,0.442 0.293,0.707Z"/><path d="M14.995,8l-0.998,0c-0.552,0 -1,0.448 -1,1c-0,0.552 0.448,1 1,1l1.002,-0l0.002,1.002c0.001,0.552 0.45,0.999 1.002,0.998c0.552,-0.001 0.999,-0.45 0.998,-1.002l-0.002,-0.998l0.998,0c0.551,0 1,-0.448 1,-1c-0,-0.552 -0.449,-1 -1,-1l-1.002,0l-0.003,-1.002c-0.001,-0.552 -0.45,-0.999 -1.002,-0.998c-0.552,0.001 -0.999,0.45 -0.998,1.002l0.003,0.998Z"/><path d="M14.992,17l6.016,-0c0.552,-0 1,-0.448 1,-1c-0,-0.552 -0.448,-1 -1,-1l-6.016,-0c-0.552,-0 -1,0.448 -1,1c0,0.552 0.448,1 1,1Z"/><path d="M14.992,21l6.016,0c0.552,0 1,-0.448 1,-1c-0,-0.552 -0.448,-1 -1,-1l-6.016,0c-0.552,0 -1,0.448 -1,1c0,0.552 0.448,1 1,1Z"/><path d="M14.985,24.994l6.015,-0c0.552,-0 1,-0.448 1,-1c-0,-0.552 -0.448,-1 -1,-1l-6.015,-0c-0.552,-0 -1,0.448 -1,1c-0,0.552 0.448,1 1,1Z"/><circle cx="10.998" cy="15.958" r="1"/><circle cx="10.998" cy="19.976" r="1"/><circle cx="10.998" cy="23.994" r="1"/></svg>',
                "title" => "Ver Detalles")
    ),

    "Administrativo"=> array(
        array ( "nombre" => "view",
                "svg" => '<svg height="100%" style="fill-rule:evenodd;clip-rule:evenodd;stroke-linejoin:round;stroke-miterlimit:2;" version="1.1" viewBox="0 0 32 32" width="100%" xml:space="preserve" xmlns="http://www.w3.org/2000/svg" xmlns:serif="http://www.serif.com/" xmlns:xlink="http://www.w3.org/1999/xlink"><path d="M25.912,5c-0,-0.796 -0.316,-1.559 -0.879,-2.121c-0.562,-0.563 -1.325,-0.879 -2.121,-0.879c-3.431,-0 -10.4,-0 -13.831,-0c-0.795,-0 -1.558,0.316 -2.121,0.879c-0.563,0.562 -0.879,1.325 -0.879,2.121l0,22c0,0.796 0.316,1.559 0.879,2.121c0.563,0.563 1.326,0.879 2.121,0.879c3.431,0 10.4,0 13.831,0c0.796,0 1.559,-0.316 2.121,-0.879c0.563,-0.562 0.879,-1.325 0.879,-2.121l-0,-22Zm-2,-0l-0,22c-0,0.265 -0.105,0.52 -0.293,0.707c-0.188,0.188 -0.442,0.293 -0.707,0.293l-13.831,-0c-0.265,0 -0.519,-0.105 -0.707,-0.293c-0.187,-0.187 -0.293,-0.442 -0.293,-0.707c0,-0 0,-22 0,-22c0,-0.265 0.106,-0.52 0.293,-0.707c0.188,-0.188 0.442,-0.293 0.707,-0.293l13.831,-0c0.265,-0 0.519,0.105 0.707,0.293c0.188,0.187 0.293,0.442 0.293,0.707Z"/><path d="M14.995,8l-0.998,0c-0.552,0 -1,0.448 -1,1c-0,0.552 0.448,1 1,1l1.002,-0l0.002,1.002c0.001,0.552 0.45,0.999 1.002,0.998c0.552,-0.001 0.999,-0.45 0.998,-1.002l-0.002,-0.998l0.998,0c0.551,0 1,-0.448 1,-1c-0,-0.552 -0.449,-1 -1,-1l-1.002,0l-0.003,-1.002c-0.001,-0.552 -0.45,-0.999 -1.002,-0.998c-0.552,0.001 -0.999,0.45 -0.998,1.002l0.003,0.998Z"/><path d="M14.992,17l6.016,-0c0.552,-0 1,-0.448 1,-1c-0,-0.552 -0.448,-1 -1,-1l-6.016,-0c-0.552,-0 -1,0.448 -1,1c0,0.552 0.448,1 1,1Z"/><path d="M14.992,21l6.016,0c0.552,0 1,-0.448 1,-1c-0,-0.552 -0.448,-1 -1,-1l-6.016,0c-0.552,0 -1,0.448 -1,1c0,0.552 0.448,1 1,1Z"/><path d="M14.985,24.994l6.015,-0c0.552,-0 1,-0.448 1,-1c-0,-0.552 -0.448,-1 -1,-1l-6.015,-0c-0.552,-0 -1,0.448 -1,1c-0,0.552 0.448,1 1,1Z"/><circle cx="10.998" cy="15.958" r="1"/><circle cx="10.998" cy="19.976" r="1"/><circle cx="10.998" cy="23.994" r="1"/></svg>',
                "title" => "Ver Detalles")
                ),

    "AdminEfector"=> array(
        array ( "nombre" => "view",
                "svg" => '<svg height="100%" style="fill-rule:evenodd;clip-rule:evenodd;stroke-linejoin:round;stroke-miterlimit:2;" version="1.1" viewBox="0 0 32 32" width="100%" xml:space="preserve" xmlns="http://www.w3.org/2000/svg" xmlns:serif="http://www.serif.com/" xmlns:xlink="http://www.w3.org/1999/xlink"><path d="M25.912,5c-0,-0.796 -0.316,-1.559 -0.879,-2.121c-0.562,-0.563 -1.325,-0.879 -2.121,-0.879c-3.431,-0 -10.4,-0 -13.831,-0c-0.795,-0 -1.558,0.316 -2.121,0.879c-0.563,0.562 -0.879,1.325 -0.879,2.121l0,22c0,0.796 0.316,1.559 0.879,2.121c0.563,0.563 1.326,0.879 2.121,0.879c3.431,0 10.4,0 13.831,0c0.796,0 1.559,-0.316 2.121,-0.879c0.563,-0.562 0.879,-1.325 0.879,-2.121l-0,-22Zm-2,-0l-0,22c-0,0.265 -0.105,0.52 -0.293,0.707c-0.188,0.188 -0.442,0.293 -0.707,0.293l-13.831,-0c-0.265,0 -0.519,-0.105 -0.707,-0.293c-0.187,-0.187 -0.293,-0.442 -0.293,-0.707c0,-0 0,-22 0,-22c0,-0.265 0.106,-0.52 0.293,-0.707c0.188,-0.188 0.442,-0.293 0.707,-0.293l13.831,-0c0.265,-0 0.519,0.105 0.707,0.293c0.188,0.187 0.293,0.442 0.293,0.707Z"/><path d="M14.995,8l-0.998,0c-0.552,0 -1,0.448 -1,1c-0,0.552 0.448,1 1,1l1.002,-0l0.002,1.002c0.001,0.552 0.45,0.999 1.002,0.998c0.552,-0.001 0.999,-0.45 0.998,-1.002l-0.002,-0.998l0.998,0c0.551,0 1,-0.448 1,-1c-0,-0.552 -0.449,-1 -1,-1l-1.002,0l-0.003,-1.002c-0.001,-0.552 -0.45,-0.999 -1.002,-0.998c-0.552,0.001 -0.999,0.45 -0.998,1.002l0.003,0.998Z"/><path d="M14.992,17l6.016,-0c0.552,-0 1,-0.448 1,-1c-0,-0.552 -0.448,-1 -1,-1l-6.016,-0c-0.552,-0 -1,0.448 -1,1c0,0.552 0.448,1 1,1Z"/><path d="M14.992,21l6.016,0c0.552,0 1,-0.448 1,-1c-0,-0.552 -0.448,-1 -1,-1l-6.016,0c-0.552,0 -1,0.448 -1,1c0,0.552 0.448,1 1,1Z"/><path d="M14.985,24.994l6.015,-0c0.552,-0 1,-0.448 1,-1c-0,-0.552 -0.448,-1 -1,-1l-6.015,-0c-0.552,-0 -1,0.448 -1,1c-0,0.552 0.448,1 1,1Z"/><circle cx="10.998" cy="15.958" r="1"/><circle cx="10.998" cy="19.976" r="1"/><circle cx="10.998" cy="23.994" r="1"/></svg>',
                "title" => "Ver Detalles")
    )            
);
?>
<div class="guardia-index">
<div class="card">
    <div class="card-header bg-soft-info">
        <h3><?= Html::encode($this->title) ?></h3>        
    </div>
    <div class="card-body">
        <p style="display: flex;justify-content: end;">
            <?= Html::a('Nuevo Paciente', ['create'], ['class' => 'btn btn-success']) ?>
        </p>
        <div class="custom-table-effect table-responsive  border rounded">
            <div id="datatable_wrapper" class="dataTables_wrapper dt-bootstrap5 no-footer">
                <div class="table-responsive my-3">
                    <?php Pjax::begin(); ?>
                    <?php // echo $this->render('_search', ['model' => $searchModel]); ?>

                    <?= GridView::widget([
                        'dataProvider' => $dataProvider,
                        'filterModel' => $searchModel,
                        'class'=> 'table-responsive my-3',
                        'columns' => [
                            //['class' => 'yii\grid\SerialColumn'],

                            //'id',
                            [
                                'label' => 'Paciente',
                                'attribute' => 'id_persona',
                                'format' => 'raw',
                                'value' => function ($data) use($acciones) {                   
                                    if($data->paciente){
                                        $nombrePaciente = $data->paciente->getNombreCompleto(Persona::FORMATO_NOMBRE_A_OA_N_ON);
                                        $nombreAcciones = '<div class="d-flex align-items-center"><div class="media-support-info">';
                                        $nombreAcciones .= "<h5 class='iq-sub-label'>".$nombrePaciente."</h5>".
                                            "<div class='d-grid gap-card grid-cols-5 mt-3'>";
                                        foreach($acciones[$_SESSION['__userRoles'][0]] as 
                                        $clave => $valor){
                                            $nombreAcciones .= "<a class='btn btn-primary btn-icon rounded-pill' href='".$acciones[$_SESSION['__userRoles'][0]][$clave]['nombre']."/".$data->id."' role='button' title='".$acciones[$_SESSION['__userRoles'][0]][$clave]['title']."'>
                                                                    <span class='btn-inner'>
                                                                       ".$acciones[$_SESSION['__userRoles'][0]][$clave]['svg']
                                                                    ."</span>
                                                                </a>"; 
                                        }                              
                                        return $nombreAcciones .= "</div></div></div>";
                           
                                    }else{
                                        return 'No definida';
                                    }
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
                                'attribute' => 'fecha',
                                'format' => 'text',
                                'filter' => '<div class="drp-container input-group"><span class="input-group-addon"></span>' .
                                    DateRangePicker::widget([
                                        'name'  => 'GuardiaBusqueda[fecha]',
                                        'options' => ['class' => 'form-control', 'placeholder'=>'Filtrar por fecha'],
                                        'pluginOptions' => [
                                            'locale' => [
                                                'separator' => ' - ',
                                            ],
                                            'opens' => 'right'
                                        ]
                                    ]) . '</div>',
                                'label' => 'Fecha',
                                'value' => function ($data) {
                                    return Yii::$app->formatter->asDate($data->fecha, 'dd/MM/yyyy');
                                },
                            ],
                            'hora',
                            
                            
                            [
                                'label' => 'Domicilio',
                                'attribute' => 'id_persona',
                                'value' => function ($data) {                   
                                    return ( $data->paciente->getDomicilioActivo())? $data->paciente->domicilioActivo->domicilioCompleto: 'No definida';
                                }
                            ],            
                            
                            //'id_rrhh_asignado',
                            [
                                'attribute' => 'id_rrhh_asignado',
                                'label' => 'RRHH', 
                                'value' => function ($data) {
                                    //hago esta pregunta porque hasta ese id se estaban guardando id de rrhh de efector y no de servicio. fix hecho el 30/10/24
                                    if($data->id <17002){

                                    return $data->id_rrhh_asignado? $data->rrhhEfector->persona->getNombreCompleto(Persona::FORMATO_NOMBRE_A_OA_N_ON) : 'NO';

                                } else{

                                    return $data->id_rrhh_asignado? $data->rrhhServicio->persona->getNombreCompleto(Persona::FORMATO_NOMBRE_A_OA_N_ON) : 'NO';
                                }

                                }               
                                
                            ],
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
