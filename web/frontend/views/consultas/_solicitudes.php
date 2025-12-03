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
            <h4 class="card-title">Solicitud de Prácticas/Derivaciones</h4>
        </div>
    </div>
    <div class="card-body">
        <?php
        DynamicFormWidget::begin([
            'widgetContainer' => 'dynamicform_wrapper_practica_solicitadas', // required: only alphanumeric characters plus "_" [A-Za-z0-9_]
            'widgetBody' => '.container-items_practicas_solicitadas', // required: css class selector
            'widgetItem' => '.item_practicas_solicitadas', // required: css class
            'limit' => 10, // the maximum times, an element can be cloned (default 999)
            'min' => 1, // 0 or 1 (default 1)
            'insertButton' => '.add-item_practicas_solicitadas', // css class
            'deleteButton' => '.remove-item_practicas_solicitadas', // css class
            'model' => $modelConsultaPracticasSolicitadas[0],
            'formId' => 'dynamic-form',
            'formFields' => [
                'id_persona',
                'id_detalle_practicas',
                'fecha',
            ],
        ]);
        ?>
        <div class="container-items_practicas_solicitadas">
            <?php foreach ($modelConsultaPracticasSolicitadas as $i => $model_c_p_s): ?>
                <div class="item_practicas_solicitadas row mb-3">
                    <div class="col pe-0">                
                        <div class="row">
                            <div class="col-md-2">
                                <?php /*?>
                                <?= $form->field($model_c_p_s, "[{$i}]tipo")->dropDownList(ConsultaPracticas::PRACTICAS_TIPOS, ['prompt' => '- Tipo -'])->label(false) ?>
                                <?php */?>
                                <?php $data = !$model_c_p_s->id_servicio ? [] : [$model_c_p_s->id_servicio => $model_c_p_s->servicio->nombre]; ?> 
                                <?= 
                                    $form->field($model_c_p_s, "[{$i}]servicio")->widget(Select2::classname(), [
                                        'data' => $data,
                                        'theme' => 'bootstrap',
                                        'language' => 'es',
                                        'options' => ['placeholder' => '- Servicio -'],
                                        'pluginOptions' => [
                                            'minimumInputLength' => 4,
                                            'ajax' => [
                                                'url' => Url::to(['servicios/search']),
                                                'dataType' => 'json',
                                                'delay'=> 500,
                                                'data' => new JsExpression('function(params) { return {q:params.term}; }'),
                                                'cache' => true
                                            ],
                                        ],
                                    ])->label(false);
                                ?>
                            </div>
                            <div class="col-md-3">
                                <?php /*?>
                                <?= $form->field($model_c_p_s, "[{$i}]tipo")->dropDownList(ConsultaPracticas::PRACTICAS_TIPOS, ['prompt' => '- Tipo -'])->label(false) ?>
                                <?php */?>
                                <?php $data = !$model_c_p_s->id_efector ? [] : [$model_c_p_s->id_efector => $model_c_p_s->efector->nombre]; ?> 
                                <?= 
                                    $form->field($model_c_p_s, "[{$i}]efector")->widget(Select2::classname(), [
                                        'data' => $data,
                                        'theme' => 'bootstrap',
                                        'language' => 'es',
                                        'options' => ['placeholder' => '- Efefctor -'],
                                        'pluginOptions' => [
                                            'minimumInputLength' => 4,
                                            'ajax' => [
                                                'url' => Url::to(['efectores/search']),
                                                'dataType' => 'json',
                                                'data' => new JsExpression('function(params) { return {q:params.term}; }'),
                                                'cache' => true
                                            ],
                                        ],
                                    ])->label(false);
                                ?>
                            </div>                            
                            <div class="col-md-6">
                                <?php $data = !$model_c_p_s->codigoSnomed ? [] : [$model_c_p_s->codigo => $model_c_p_s->codigoSnomed->term]; ?>               
                                <?= 
                                    $form->field($model_c_p_s, "[{$i}]codigo")->widget(Select2::classname(), [
                                        'data' => $data,
                                        'theme' => 'bootstrap',
                                        'language' => 'es',
                                        'options' => ['placeholder' => '-Seleccione la Práctica-'],
                                        'pluginOptions' => [
                                            'minimumInputLength' => 4,
                                            'ajax' => [
                                                'url' => Url::to(['snowstorm/practicas']),
                                                'dataType' => 'json',
                                                'delay'=> 500,
                                                'data' => new JsExpression('function(params) { return {q:params.term}; }'),
                                                'cache' => true
                                            ],
                                            'pluginEvents' => [
                                                "select2:select" => 'function() { return document.getElementById("['.$i.']termino_procedimiento_solicitado").value = $("#select2-consultapracticassolicitadas-'.$i.'-codigo-container").text(); }',
                                            ]
                                        ],
                                    ])->label(false);
                                ?>
                                <?= Html::hiddenInput(
                                        "[{$i}]termino_procedimiento_solicitado", 
                                        !$model_c_p_s->codigoSnomed ? '' : $model_c_p_s->codigoSnomed->term,
                                        ['id' => "[{$i}]termino_procedimiento_solicitado"]);
                                ?>
                            </div>
                            <div class="col-md-3">
                                <?php
                                /*
                                    $data = [];
                                    if (!is_null($model_c_p_s->dirigido_a) && $model_c_p_s->dirigido_a != "") {
                                        $data = [$model_c_p_s->dirigido_a => $model_c_p_s->rrhhDerivado->persona->apellido . ', ' . $model_c_p_s->rrhhDerivado->persona->nombre];
                                    }

                                    echo $form->field($model_c_p_s, 'dirigido_a')->widget(Select2::classname(), [
                                        'data' => $data,
                                        'theme' => 'bootstrap',
                                        'language' => 'es',
                                        'options' => ['placeholder' => 'Seleccione el Profesional que realizará la práctica'],
                                        'pluginOptions' => [
                                            'allowClear' => true,
                                            'minimumInputLength' => 3,
                                            'ajax' => [
                                                'url' => Url::to(['rrhh/rrhh-autocomplete']),
                                                'dataType' => 'json',
                                                'data' => new JsExpression('function(params) { return {q:params.term}; }'),
                                            ],
                                        ],
                                    ])->label(false);
                                    */
                                ?>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-12">
                                <?= $form->field($model_c_p_s, "[{$i}]indicaciones")->textArea(); ?>
                            </div>
                        </div>
                    </div>
                    <div class="col col-sm-2 text-center mt-5 ps-0">
                        <button type="button" class="btn btn-outline-link rounded-pill float-xl-end remove-item_practicas_solicitadas" data-bs-toggle="tooltip" data-bs-placement="right" data-bs-original-title="Quitar esta fila">
                            <svg class="icon-32" width="32" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M14.3955 9.59497L9.60352 14.387" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
                                <path d="M14.3971 14.3898L9.60107 9.59277" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
                                <path fill-rule="evenodd" clip-rule="evenodd" d="M16.3345 2.75024H7.66549C4.64449 2.75024 2.75049 4.88924 2.75049 7.91624V16.0842C2.75049 19.1112 4.63549 21.2502 7.66549 21.2502H16.3335C19.3645 21.2502 21.2505 19.1112 21.2505 16.0842V7.91624C21.2505 4.88924 19.3645 2.75024 16.3345 2.75024Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
                            </svg>
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="row">
            <div class="col-xs-12">
                <button type="button" class="add-item_practicas_solicitadas mt-2 btn btn-info rounded-pill float-xl-end text-white">
                    <i class="bi bi-plus-circle text-white"></i> Sumar Práctica
                </button>
            </div>
        </div>
        <?php DynamicFormWidget::end(); ?>
    </div>
</div>