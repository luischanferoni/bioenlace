<?php

use yii\helpers\ArrayHelper; 
use yii\helpers\Url;
use yii\helpers\Html;
use yii\web\JsExpression;

use kartik\select2\Select2;
use wbraganca\dynamicform\DynamicFormWidget;

use common\models\Cie10;
use common\models\Consulta;
use common\models\ConsultaPracticas;

?>
<!------ Formulario Dinámico ------->
<div class="card mb-3 border border-2">
    <div class="card-header d-flex justify-content-between">
        <div class="header-title">
            <h4 class="card-title">Prácticas</h4>
        </div>
    </div>
    <div class="card-body">
        <?php /*
        DynamicFormWidget::begin([
            'widgetContainer' => 'dynamicform_wrapper_practica', // required: only alphanumeric characters plus "_" [A-Za-z0-9_]
            'widgetBody' => '.container-items_practicas', // required: css class selector
            'widgetItem' => '.item_practicas', // required: css class
            'limit' => 10, // the maximum times, an element can be cloned (default 999)
            'min' => 1, // 0 or 1 (default 1)
            'insertButton' => '.add-item_practicas', // css class
            'deleteButton' => '.remove-item_practicas', // css class
            'model' => $modelosConsultaPracticas[0],
            'formId' => 'dynamic-form',
            'formFields' => [
                'id_persona',
                'id_detalle_practicas',
                'fecha',
            ],
        ]); */
        ?>
        <div class="container-items_practicas">
            <?php // foreach ($model_consulta_practicas as $i => $model_c_p): ?>
                <div class="item_practicas row mb-3">
                    <div class="col">                
                        <div class="row">
                            <?php /*?>
                            <div class="col-sm-3">
                                <?= $form->field($model_c_p, "[{$i}]tipo")->dropDownList(ConsultaPracticas::PRACTICAS_TIPOS, ['prompt' => '- Tipo -'])->label(false) ?>
                            </div>
                            <?php */?>
                            <div class="col-sm-12">
                                <?php 
                                //echo var_dump($modelConsultaSintomas);exit;
                                $data = array();
                                $text= array();
                                #echo var_dump($modelConsultaPracticas);exit;
                                if($idConsulta){
                                    foreach($modelConsultaPracticas as $practica){
                                            $predata = ($practica->codigoSnomed) ? (array)($practica->codigoSnomed) : [];                   
                                            $dat =  ArrayHelper::map($predata, 'conceptId', 'term') ;
                                            foreach($dat as $key => $value):
                                                $data[$key] =  $value;
                                                $text[] = $key;
                                            endforeach;
                                            array_pop($text);
                                            array_pop($data);
                                    }
                                }   
                                #$data = !$modelConsultaPracticas->codigoSnomed ? [] : [$modelConsultaPracticas->codigo => $modelConsultaPracticas->codigoSnomed->term]; ?>               
                                <?= 
                                    $form->field(new consultaPracticas, "select2_codigo")->widget(Select2::classname(), [
                                        'data' => $data,
                                        'size' => Select2::LARGE,
                                        'options' => ['placeholder' => '- Seleccione la Práctica -', 'multiple' => 'true', 'value' => $text],
                                        'pluginOptions' => [
                                            'initialize' => true,
                                            'minimumInputLength' => 4,
                                            'ajax' => [
                                                'url' => Url::to(['snowstorm/practicas']),
                                                'dataType' => 'json',
                                                'delay'=> 500,
                                                'data' => new JsExpression('function(params) { return {q:params.term}; }'),
                                                'cache' => true
                                            ],
                                        ],
                                        'pluginEvents' => [
                                            "select2:select" => 'function() {
                                                let i = 0;
                                                let procedimientosSeleccionados = [];
                                                datos = $(this).select2("data");
                                                while (i < datos.length) {
                                                    procedimientosSeleccionados.push(datos[i].text);
                                                    i++;
                                                }
                                                $("#terminos_procedimiento").val(procedimientosSeleccionados.join());
                                            }',
                                        ]                                        
                                    ])->label(false);
                                ?>
                                <?= Html::hiddenInput(
                                        "terminos_procedimiento", json_encode($data), ['id' => "terminos_procedimiento"]);
                                ?>
                            </div>
                            <?php /*?>
                            <div class="col-sm-3">
                                <?= $form->field($model_c_p, "[{$i}]archivos_adjuntos[]")
                                            ->fileInput([
                                                'multiple' => true, 
                                                'accept' => 'application/pdf,application/msword,application/x-rar-compressed'])->label(false) ?>
                            </div>
                            <?php */?>
                        </div>
                        <?php /*?>
                        <div class="row">
                            <div class="col-12">
                                <?= $form->field($model_c_p, "[{$i}]informe")->textArea(['placeholder' => 'Informe'])->label(false); ?>
                            </div>
                        </div>
                        <?php */?>
                    </div>
                    <?php /*?>
                    <div class="col col-sm-1 text-center mt-0 ps-0">
                        <button type="button" class="btn btn-outline-link rounded-pill float-xl-end remove-item_practicas" data-bs-toggle="tooltip" data-bs-placement="right" data-bs-original-title="Quitar esta fila">
                            <svg class="icon-32" width="32" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M14.3955 9.59497L9.60352 14.387" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
                                <path d="M14.3971 14.3898L9.60107 9.59277" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
                                <path fill-rule="evenodd" clip-rule="evenodd" d="M16.3345 2.75024H7.66549C4.64449 2.75024 2.75049 4.88924 2.75049 7.91624V16.0842C2.75049 19.1112 4.63549 21.2502 7.66549 21.2502H16.3335C19.3645 21.2502 21.2505 19.1112 21.2505 16.0842V7.91624C21.2505 4.88924 19.3645 2.75024 16.3345 2.75024Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
                            </svg>
                        </button>
                    </div>
                    <?php */?>
                </div>
            <?php // endforeach; ?>
        </div>
        <?php /*?>
        <div class="row">
            <div class="col-xs-12">
                <button type="button" class="add-item_practicas mt-2 btn btn-info rounded-pill float-xl-end text-white">
                    <i class="bi bi-plus-circle text-white"></i> Sumar Práctica
                </button>
            </div>
        </div>
        <?php */?>
        <?php // DynamicFormWidget::end(); ?>
    </div>
</div>