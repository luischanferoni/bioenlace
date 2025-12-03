<?php

use yii\helpers\Html;
use yii\grid\GridView;

$this->title = 'Historial de Consultas';
$this->params['breadcrumbs'][] = $this->title;

$nombre_paciente = common\models\Consulta::getPersona(Yii::$app->getRequest()->getQueryParam('id'));
?>
<div class="consulta-index">

<!--    <h1><?php //= Html::encode($this->title) ?></h1>-->
    <h3>Paciente: <span style="font-style: italic"><?= $nombre_paciente?></span></h3>
    

    <?= GridView::widget([
        'dataProvider' => $dataProvider,
//        'filterModel' => $searchModel,
        'columns' => [
            ['class' => 'yii\grid\SerialColumn'],

//            'id_consulta',
//            'id_turnos',
//            [
//                'attribute'=> 'id_turnos',
//                'label'=> 'Paciente',
//                'value'=>function($data){
//                    $consulta_turnos = \common\models\Turno::findOne(['id_turnos'=>$data->id_turnos]);
//                    $id_persona = $consulta_turnos->id_persona;
//                    $model_persona = common\models\Persona::findOne($id_persona);
//                    $nombre = $model_persona->nombre;
//                    $apellido = $model_persona->apellido;
//                    return $apellido.', '.$nombre;
//                }
//            ],
//            'hora',
            [
                'attribute'=> 'hora',
                'label'=> 'Fecha | Hora',
                'value'=>function($data){
                    $consulta_turnos = \common\models\Turno::findOne(['id_turnos'=>$data->id_turnos]);
                    $hora = $consulta_turnos->hora;
                    $fecha = $consulta_turnos->fecha;
                    return $fecha.' | '.$hora;
                }
            ],
//            'consulta_inicial',
//            'id_tipo_ingreso',
            [
                'attribute'=> 'motivo_consulta',
                'label'=> 'Motivo',
               
            ],
            [
                'attribute'=> 'id_turnos',
                'label'=> 'Profesional',
                'value'=>function($data){
                    //TODO: Mejorar busqueda de profesion y especialidad, tal vez hay que ver en que agenda se le dio el turno para obtener mejor la especialidad
                    $profesion = $data->turno->rrhhEfector->persona->profesionalSalud[0]->especialidad->profesion->nombre;
                    $especialidad = $data->turno->rrhhEfector->persona->profesionalSalud[0]->especialidad->nombre;
                return $data->turno->rrhhEfector->persona->nombre.' '.$data->turno->rrhhEfector->persona->apellido ." ($profesion - $especialidad)";
                }
            ],
            [
                'attribute'=> 'id_turnos',
                'label'=> 'Efector',
                'value'=>function($data){
                return common\models\Consulta::getEfector($data->id_turnos);
                }
            ],
            // 'motivo_consulta:ntext',
            // 'observacion:ntext',
            // 'control_embarazo',

//            ['class' => 'yii\grid\ActionColumn'],
              [
                 'attribute'=>'id_consulta',
                 'label' => '',
                 'format'=>'raw',
                 'value' => function($data) {
                                    //return  Html::a('Ver', ['consultas/view', 'id' => $data->id_consulta])." | ".Html::a('Editar', ['consultas/update', 'id' => $data->id_consulta]);
                                    return  Html::a('Detalle', ['consultas/view', 'id' => $data->id_consulta]);
                            }
             ],
            
        ],
    ]); ?>

</div>
