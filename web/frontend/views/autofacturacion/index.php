<?php

use yii\helpers\Url;
use yii\grid\GridView;
use yii\widgets\LinkPager;

use common\models\Consulta;
use common\models\Persona;


$this->title = 'Autofacturación';

?>

<div class="col-12">
    <div class="card">
        <div class="card-header">
            <div class="header-title">
                <h4 class="card-title">Listado de Consultas</h4>
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
                'afterRow' => function($model, $key, $index) {
                    

                    if ($model->autofacturacion) {                                             

                        $vista = Yii::$app->controller->renderPartial('_mapeado', [
                            'id_consulta' => $model->id_consulta,
                            'autofacturacion' => $model->autofacturacion,
                            'beneficiarios' => json_decode($model->autofacturacion->beneficiarios),
                            'codigos' => $model->autofacturacion->codigos
                        ]);
                    } else {
                        $vista = "Aún no se han generado los códigos de sumar para esta consulta.";
                    }

                    return '<tr id="div_mapear_<?=$id_consulta?>">
                                <td colspan="5">'.$vista.'</td>
                            </tr>';
                },
                'columns' => [
                    ['class' => 'yii\grid\CheckboxColumn'],
                    [
                        'label' => 'Fecha Consulta',
                        'value' => function($data) { 
                            return Yii::$app->formatter->asDate($data->created_at, 'dd/MM/yyyy');
                        }
                    ],
                    [
                        'label' => 'Paciente',
                        'value' => function($data) { 
                            return $data->paciente->getNombreCompleto(Persona::FORMATO_NOMBRE_A_N_D);
                        }
                    ],
                    [
                        'contentOptions' => ['class' => 'text-wrap'],
                        'format' => 'raw',
                        'label' => 'Diagnósticos',
                        'value' => function($data) {
                            $diagnosticos = [];
                            foreach($data->diagnosticoConsultas as $diagnostico) {
                                $diagnosticos[] = isset($diagnostico->codigoSnomed->term) ? $diagnostico->codigoSnomed->term:"";
                            }
                            return '<ul><li>'.implode("</li><li>", $diagnosticos).'</li></ul>';
                        }
                    ],
                    [
                        'contentOptions' => ['class' => 'text-wrap'],
                        'format' => 'raw',
                        'label' => 'Prácticas',
                        'value' => function($data) {
                            $practicas = [];
                            foreach($data->consultaPracticas as $practica) {
                                $practicas[] = $practica->codigoSnomed->term;
                            }
                            return '<ul><li>'.implode("</li><li>", $practicas).'</li></ul>';
                        }
                    ],
                ]
            ]);
            ?>
            <div class="w-100">
                <button class="btn float-end btn-warning position-relative py-2 guardar_mapeo" data-id="">Enviar todos los seleccionados a Sumar</button>        
            </div>
            
        </div>
    </div>
</div>

<?php
$this->registerJs(
    "
        $(document).ready(function() {
        
            $(document).on('click', '.mapear', function() {
                let id_consulta = $(this).attr('data-id');
                $.ajax({
                    url: '" . Url::to(['autofacturacion/mapear-sumar']) . "',
                    type: 'POST',
                    data: {'id_consulta': id_consulta},
                    success: function (data) {
                        if(!data.error){
                            $('#div_mapear_' + id_consulta).html(data.message);
                            //location.reload();
                        }else{
                            $('body').append('<div class=\"alert alert-success\" role=\"alert\">'
                                +'<i class=\"fa fa-exclamation fa-1x\"></i> Se produjo un error al intentar mapear la consulta</div>');                            
                        }
                    },
                    error: function () {
                            $('body').append('<div class=\"alert alert-success\" role=\"alert\">'
                                +'<i class=\"fa fa-exclamation fa-1x\"></i> Error inesperado</div>'); 
                    }
                });
            });            

            $(document).on('click', '.guardar_mapeo', function() {

                let id_consulta = $(this).attr('data-id');
                let radioBeneficiario = $('.' + id_consulta + '_radioBeneficiario');
                let valueBeneficiario = false;

                for (let i = 0; i < radioBeneficiario.length; i++) {
                    if (radioBeneficiario[i].checked) {
                        valueBeneficiario = radioBeneficiario[i].value;
                        break;
                    }
                }

                if (valueBeneficiario == false) {
                    alertaFlotante('Seleccione el beneficiario', 'danger');
                }

                let radioCodigos = $('.' + id_consulta + '_radio');
                let valueCodigos = false;
                for (let i = 0; i < radioCodigos.length; i++) {
                    if (radioCodigos[i].checked) {
                        valueCodigos = radioCodigos[i].value;
                        break;
                    }
                }

                if (valueCodigos == false) {
                    alertaFlotante('Seleccione el codigo a guardar', 'danger');
                }

                $.ajax({
                    url: '" . Url::to(['autofacturacion/enviar-sumar']) . "',
                    type: 'POST',
                    data: {'id_consulta': id_consulta, 'codigo': valueCodigos, 'beneficiario':valueBeneficiario},
                    success: function (data) {
                        if(!data.error){
                            alertaFlotante(data.message, 'success');
                            location.reload();
                        }else{
                            $('body').append('<div class=\"alert alert-success\" role=\"alert\">'
                                +'<i class=\"fa fa-exclamation fa-1x\"></i> Se produjo un error al intentar enviar la consulta a sumar</div>');                            
                        }
                    },
                    error: function () {
                            $('body').append('<div class=\"alert alert-success\" role=\"alert\">'
                                +'<i class=\"fa fa-exclamation fa-1x\"></i> Error inesperado</div>'); 
                    }
                });

            }); 

        });
    "
);