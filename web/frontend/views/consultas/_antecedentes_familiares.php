<?php
use kartik\select2\Select2;
use wbraganca\dynamicform\DynamicFormWidget;
use common\models\Cie10;
use yii\helpers\ArrayHelper; 
use common\models\Consulta;
use yii\helpers\Url;
use yii\helpers\Html;
use yii\web\JsExpression;
?>
 <!------ Formulario Dinámico ------->
 <div class="card mb-3 border border-2">
    <div class="card-header d-flex justify-content-between">
        <div class="header-title">
            <h4 class="card-title">Antecedentes Familiares</h4>
        </div>
    </div>
    <div class="card-body">
        <?php
        DynamicFormWidget::begin([
            'widgetContainer' => 'dynamicform_wrapper_antecedente_familiar', // required: only alphanumeric characters plus "_" [A-Za-z0-9_]
            'widgetBody' => '.container-items_antecedentes_familiares', // required: css class selector
            'widgetItem' => '.item_antecedentes_familiares', // required: css class
            'limit' => 10, // the maximum times, an element can be cloned (default 999)
            'min' => 0, // 0 or 1 (default 1)
            'insertButton' => '.add-item_antecedentes_familiares', // css class
            'deleteButton' => '.remove-item_antecedentes_familiares', // css class
            'model' => $model_personas_antecedente_2[0],
            'formId' => 'dynamic-form',
            'formFields' => [
                'id_antecedente',
                'nombre',                       
            ],
        ]);
        ?>

        <div class="container-items_antecedentes_familiares">
            <?php foreach ($model_personas_antecedente_2 as $i => $model_p_a_f): ?>
                <div class="item_antecedentes_familiares row mb-3">
                    <div class="col-sm-10 pe-0">
                        <?php $data = !$model_p_a_f->snomedSituacion ? [] : [$model_p_a_f->id_snomed_situacion => $model_p_a_f->snomedSituacion->term]; ?>
                        
                        <?= 
                            $form->field($model_p_a_f, "[{$i}]id_snomed_situacion")->widget(Select2::classname(), [
                                'data' => $data,
                                'theme' => 'bootstrap',
                                'language' => 'es',
                                'options' => ['placeholder' => '-Seleccione el antecedente-'],
                                'pluginOptions' => [
    //                                                'allowClear' => true
                                    'minimumInputLength' => 3,
                                    'ajax' => [
                                        'url' => Url::to(['snowstorm/antecedentesfamiliares']),
                                        'dataType' => 'json',
                                        'delay'=> 500,
                                        'data' => new JsExpression('function(params) { return {q:params.term}; }')
                                    ],                                                 
                                ],
                                'pluginEvents' => [
                                    "select2:select" => 'function() { return document.getElementById("['.$i.']termino_antecedente_familiar").value = $("#select2-personasantecedentefamiliar-'.$i.'-codigo-container").text(); }',
                                ]                                
                            ])->label('Descripción')
                        ?>
                        <?= Html::hiddenInput(
                                "[{$i}]termino_antecedente_familiar",
                                !$model_p_a_f->snomedSituacion ? '' : $model_p_a_f->snomedSituacion->term,
                                ['id' => "[{$i}]termino_antecedente_familiar"]);
                        ?>                        
                    </div>
                    <div class="col-sm-2 col text-center ps-0">
                        <button type="button" class="btn btn-outline-link rounded-pill float-xl-end remove-item_antecedentes_familiares" data-bs-toggle="tooltip" data-bs-placement="right" data-bs-original-title="Quitar esta fila">
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
                <button type="button" class="add-item_antecedentes_familiares mt-2 btn btn-info rounded-pill float-xl-end text-white">
                    <i class="bi bi-plus-circle text-white"></i> Agregar Antecedente Familiar
                </button>
            </div>
        </div>
        <?php DynamicFormWidget::end(); ?>
    </div>
</div>