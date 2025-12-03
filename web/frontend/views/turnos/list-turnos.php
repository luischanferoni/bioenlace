<?php

use yii\helpers\Html;
use yii\grid\GridView;
use common\models\Persona;
use common\models\Turno;


$this->title = 'Consulta de Turnos Pendientes';
$this->params['breadcrumbs'][] = $this->title;
$estados = array(Turno::ESTADO_PENDIENTE => 'bg-soft-warning p-2 text-warning', Turno::ESTADO_CANCELADO => 'bg-secondary', Turno::ESTADO_EN_ATENCION => 'bg-success', Turno::ESTADO_ATENDIDO => 'bg-info', Turno::ESTADO_SIN_ATENDER => 'bg-danger');
?>
<div class="turnos-pendientes">

    <div class="mb-5">
        <h2><?= Html::encode($this->title) ?></h2>
    </div>

    <?php
    echo $this->render('_search_publico', ['model' => $searchModel]);
    ?>
    <?php $gridColumns = [
        [
            'label' => 'Fecha',
            'attribute' => 'fecha',
            'format' => 'text',
            'value' => function ($data) {
                return Yii::$app->formatter->asDate($data->fecha, 'dd/MM/yyyy');
            },
        ],
        [
            'attribute' => 'hora',
            'filter' => false,

        ],
        [
            'label' => 'Estado',
            'attribute' => 'estado',
            'format' => 'raw',
            'value' => function ($data) use ($estados) {
                $ref = ($data->id_consulta_referencia != 0) ? '<span class="badge bg-info">Referencia</span>' : '';
                if ($data->fecha < '2024-03-28' && $data->atendido == Turno::ATENDIDO_SI) {

                    return $ref . ' <span class="badge ' . $estados[Turno::ESTADO_ATENDIDO] . '">' . strtoupper(Turno::ESTADOS[Turno::ESTADO_ATENDIDO]) . '</span>';
                } else {
                    return ($data->estado) ? $ref . ' <span class="badge ' . $estados[$data->estado] . '">' . strtoupper(Turno::ESTADOS[$data->estado]) . '</span>' : 'No definida';
                }
            }
        ],
        [
            'label' => 'Profesional',
            'attribute' => 'id_rrhh_servicio_asignado',
            'format' => 'raw',
            //'filter'=>false,
            'value' => function ($data) {
                return $data->id_rrhh_servicio_asignado ? $data->rrhhServicioAsignado->rrhhEfector->persona->getNombreCompleto(Persona::FORMATO_NOMBRE_A_OA_N_ON) :
                    'SIN ESPECIFICAR';
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
        ],
        [
            'attribute' => 'efector.nombre',
            'label' => 'Efector',
            /*'filter' => Html::activeDropDownList(
                                                $turnos,
                                                'id_servicio_asignado',
                                                ArrayHelper::map(Servicio::find()->all(), 'id_servicio', 'nombre'),
                                                [
                                                    'class' => 'select2',
                                                    'prompt' => '- Seleccione -'
                                                ]
                                            )*/
        ]
    ]
    ?>


    <?php if ($dataProvider->getTotalCount() >= 0) { ?>

        <div class="card">
            <?php if (isset($dataProvider->getModels()[0])) { ?>
                <div class="card-header bg-soft-info">
                    <h5>Turnos de: <?= $dataProvider->getModels()[0]->persona->getNombreCompleto(Persona::FORMATO_NOMBRE_A_OA_N_ON) ?></h5>
                </div>
            <?php } ?>
            <div class="card-body">
                <div class="table-responsive my-3">

                    <?= GridView::widget([
                        'dataProvider' => $dataProvider,
                        'class' => 'table mb-0 dataTable no-footer" id="datatable" data-toggle="data-table" aria-describedby="datatable_info"',
                        'columns' => $gridColumns,
                    ]) ?>

                </div>
            </div>
        </div>

    <?php } ?>

</div>

</div>
</div>
</div>
</div>