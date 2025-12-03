<?php

use yii\helpers\Url;
use yii\helpers\Html;
use yii\web\JsExpression;

use kartik\select2\Select2;
use wbraganca\dynamicform\DynamicFormWidget;

use common\models\DiagnosticoConsulta;
?>
<!------ Formulario Dinámico ------->
<div class="card mb-3 border border-2">
    <div class="card-header d-flex justify-content-between">
        <div class="header-title">
            <h4 class="card-title">Diagnósticos</h4>
        </div>
    </div>
    <div class="card-body">
        <?php
        DynamicFormWidget::begin([
            'widgetContainer' => 'dynamicform_wrapper', // required: only alphanumeric characters plus "_" [A-Za-z0-9_]
            'widgetBody' => '.container-items_diagnostico', // required: css class selector
            'widgetItem' => '.item_diagnostico', // required: css class
            'limit' => 4, // the maximum times, an element can be cloned (default 999)
            'min' => 1, // 0 or 1 (default 1)
            'insertButton' => '.add-item_diagnostico', // css class
            'deleteButton' => '.remove-item_diagnostico', // css class
            'model' => $modelosConsultaDiagnostico[0],
            'formId' => 'dynamic-form',
            'formFields' => [
                'codigo',
                'tipo_diagnostico',
            ],
        ]);
        ?>

        <div class="container-items_diagnostico">

            <?php foreach ($modelosConsultaDiagnostico as $i => $model_d_c): ?>
                <div class="item_diagnostico row mb-3">                        
                    <div class="col-11 <?=$model->isNewRecord ? 'col-sm-7' : 'col-sm-4'?>">
                        <?php $data = !$model_d_c->codigoSnomed ? [] : [$model_d_c->codigo => $model_d_c->codigoSnomed->term]; ?>
                
                        <?= 
                            $form->field($model_d_c, "[{$i}]codigo")->widget(Select2::classname(), [
                                'data' => $data,
                                'options' => ['placeholder' => '- Escriba el Diagnóstico -', 'class' => 'diagnostico_select'],
                                'pluginOptions' => [
                                    'minimumInputLength' => 4,
                                    'ajax' => [
                                        'url' => Url::to(['snowstorm/diagnosticos']),
                                        'dataType' => 'json',
                                        'delay'=> 500,
                                        'data' => new JsExpression('function(params) { return {q:params.term}; }'),
                                        'cache' => true
                                    ],
                                ],
                                'pluginEvents' => [
                                    "select2:select" => 'function() { return document.getElementById("termino_problema['.$i.']").value = $("#select2-diagnosticoconsulta-'.$i.'-codigo-container").text(); }',
                                ]                                    
                            ])->label(false)
                        ?>
                        <?= Html::hiddenInput(
                                "termino_problema[{$i}]",
                                !$model_d_c->codigoSnomed ? '' : $model_d_c->codigoSnomed->term,
                                ['id' => "termino_problema[{$i}]"]);
                        ?>
                    </div>
                    <div class="col-3 col-sm-2">
                        <label class="btn btn-outline-secondary">
                            <input type="checkbox" name="[<?=$i?>]cronico" autocomplete="off"> Crónico
                        </label>                        
                    </div>
                    <?php if(!$model->isNewRecord) { ?>
                        <div class="col-4 col-sm-3">
                            <?= $form->field($model_d_c, "[{$i}]condition_clinical_status")->dropDownList(DiagnosticoConsulta::ESTADOS_CLINICOS, ['prompt' => '- Estado Clínico -'])->label(false) ?>
                        </div>
                    <?php } ?>
                    <div class="col-3 col-sm-2 pe-0">
                        <label class="btn btn-outline-secondary">
                            <input type="checkbox" class="mt-1" name="[<?=$i?>]condition_verification_status" autocomplete="off"> Confirmado
                        </label>
                    </div>
                    <div class="col col-sm-1 text-center ps-0">
                        <button type="button" class="btn btn-outline-link rounded-pill float-xl-end remove-item_diagnostico" data-bs-toggle="tooltip" data-bs-placement="right" data-bs-original-title="Quitar esta fila">
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
                <button type="button" class="add-item_diagnostico mt-2 btn btn-info rounded-pill float-xl-end text-white">
                    <i class="bi bi-plus-circle text-white"></i> Agregar Diagnóstico
                </button>
            </div>
        </div>
        <?php DynamicFormWidget::end(); ?>
    </div>                
</div>