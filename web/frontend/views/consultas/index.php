<?php

use yii\helpers\Html;
use yii\grid\GridView;


$this->title = 'Consultas';
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="consulta-index">

    <?php // echo $this->render('_search', ['model' => $searchModel]); 
    ?>
    <div class="text-end mb-5">
        <?= Html::a('Ver Prescripciones Médicas', ['prescripciones-medicas-por-consulta'], ['class' => 'btn btn-soft-light text-white']) ?>
    </div>

    <div class="card">
        <div class="card-body">
            <?= GridView::widget([
                'dataProvider' => $dataProvider,
                //        'filterModel' => $searchModel,
                'tableOptions' => ['class' => 'table mb-0 dataTable table-responsive  border rounded'],
                'headerRowOptions' => ['class' => 'bg-soft-primary'],
                'filterRowOptions' => ['class' => 'bg-white'],
                'pager' => ['class' => 'yii\bootstrap5\LinkPager', 'prevPageLabel' => 'Anterior', 'nextPageLabel' => 'Siguiente', 'options' => ['class' => 'pagination justify-content-center mt-5']],
                'columns' => [
                    ['class' => 'yii\grid\SerialColumn'],

                    //            'id_consulta',
                    //            'id_turnos',
                    [
                        'attribute' => 'id_turnos',
                        'label' => 'Paciente',
                        'value' => function ($data) {
                            $consulta_turnos = \common\models\Turno::findOne(['id_turnos' => $data->id_turnos]);
                            $id_persona = $consulta_turnos->id_persona;
                            $model_persona = common\models\Persona::findOne($id_persona);
                            $nombre = $model_persona->nombre;
                            $apellido = $model_persona->apellido;
                            return $apellido . ', ' . $nombre;
                        }
                    ],
                    //            'hora',
                    [
                        'attribute' => 'hora',
                        'label' => 'Hora | Fecha',
                        'value' => function ($data) {
                            $consulta_turnos = \common\models\Turno::findOne(['id_turnos' => $data->id_turnos]);
                            $hora = $consulta_turnos->hora;
                            $fecha = $consulta_turnos->fecha;
                            return $hora . ' | ' . $fecha;
                        }
                    ],
                    //            'consulta_inicial',
                    //            'id_tipo_ingreso',
                    //            [
                    //                'attribute'=> 'id_tipo_ingreso',
                    //                'value'=>function($data){
                    //                return \common\models\TipoIngreso::findOne(['id_tipo_ingreso'=>$data->id_tipo_ingreso])->nombre;
                    //                }
                    //            ],
                    // 'motivo_consulta:ntext',
                    // 'observacion:ntext',
                    // 'control_embarazo',

                    //            ['class' => 'yii\grid\ActionColumn'],
                    [
                        'attribute' => 'id_consulta',
                        'label' => '',
                        'format' => 'raw',
                        'value' => function ($data) {
                            //return  Html::a('Ver', ['consultas/view', 'id' => $data->id_consulta])." | ".Html::a('Editar', ['consultas/update', 'id' => $data->id_consulta]);
                            return  Html::a('<i class="bi bi-eye"></i> Ver', ['consultas/view', 'id' => $data->id_consulta], ['class' => 'btn btn-primary']);
                        }
                    ],

                ],
            ]); ?>
        </div>
    </div>

</div>