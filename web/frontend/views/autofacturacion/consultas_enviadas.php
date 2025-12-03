<?php

use yii\grid\GridView;
use common\models\Persona;
use kartik\daterange\DateRangePicker;
use yii\bootstrap5\ActiveForm;

$this->title = 'Consultas Enviadas';

$form = ActiveForm::begin();

?>

<div class="col-12">
    <div class="card">
        <div class="card-header">
            <div class="header-title">
                <h4 class="card-title">Listado de Consultas Enviadas a Sumar</h4>
            </div>
        </div>

        <div class="card-body">


            <?= GridView::widget([
                'dataProvider' => $dataProvider,
                'filterModel' => $searchModel,
                'summary' => '',
                'options' => ['class' => 'table-responsive'],
                'tableOptions' => ['class' => 'table table-striped table-hover table-bordered rounded'],
                'headerRowOptions' => ['class' => 'bg-primary text-white'],
                'filterRowOptions' => ['class' => 'bg-white'],
                'columns' => [
                    [
                        'label' => 'Fecha de Consulta',
                        'value' => function ($data) {
                            return Yii::$app->formatter->asDate($data->created_at, 'dd/MM/yyyy');
                        }
                    ],
                    [
                        'attribute' => 'fecha_envio',
                        'format' => 'text',
                        'filter' => '<div class="drp-container input-group"><span class="input-group-addon"></span>' .
                            DateRangePicker::widget([
                                'name'  => 'ItemOrderSearch[fecha_envio]',
                                'options' => ['class' => 'form-control', 'placeholder'=>'Filtrar por fecha'],
                                'pluginOptions' => [
                                    'locale' => [
                                        'separator' => ' - ',
                                    ],
                                    'opens' => 'right'
                                ]
                            ]) . '</div>',
                        'label' => 'Fecha de Envío',
                        'value' => function ($data) {
                            return Yii::$app->formatter->asDate($data->autofacturacion->fecha_envio, 'dd/MM/yyyy');
                        },
                    ],
                    [
                        'label' => 'ID Beneficiario',
                        'value' => function ($data) {
                            return $data->autofacturacion->id_beneficiario;
                        }
                    ],
                    [
                        'label' => 'Paciente',
                        'value' => function ($data) {
                            return $data->paciente->getNombreCompleto(Persona::FORMATO_NOMBRE_A_N_D);
                        }
                    ],
                    [
                        'label' => 'Profesional de Salud',
                        'contentOptions' => ['class' => 'text-wrap'],
                        'value' => function ($data) {
                            return isset($data->parent->id_rrhh_servicio_asignado) ? $data->parent->rrhhServicioAsignado->rrhhEfector->persona->getNombreCompleto(Persona::FORMATO_NOMBRE_A_OA_N_ON) : '';
                        }
                    ],
                    [
                        'label' => 'Facturista',
                        'contentOptions' => ['class' => 'text-wrap'],
                        'value' => function ($data) {
                            return $data->autofacturacion->rrhhEfector->persona->getNombreCompleto(Persona::FORMATO_NOMBRE_A_OA_N_ON);
                        }
                    ],
                    [
                        'label' => 'Cod. Enviado',
                        'value' => function ($data) {
                            return $data->autofacturacion->codigo_enviado;
                        }
                    ],
                ]
            ]);
            ?>
        </div>
    </div>
</div>