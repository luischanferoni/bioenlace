<?php

use yii\helpers\ArrayHelper;
use yii\helpers\Url;
use yii\helpers\Html;
use yii\web\JsExpression;
use yii\bootstrap5\ActiveForm;

use kartik\select2\Select2;
use kartik\file\FileInput;
use wbraganca\dynamicform\DynamicFormWidget;

use common\models\Cie10;
use common\models\Consulta;
use common\models\ConsultaPracticas;

use common\assets\SisseDynamicFormAsset;

SisseDynamicFormAsset::register($this);


?>
<style>
    select[readonly].select2-hidden-accessible + .select2-container {
        pointer-events: none;
        touch-action: none;
    }

    select[readonly].select2-hidden-accessible + .select2-container .select2-selection {
        background: #eee;
        box-shadow: none;
    }

    select[readonly].select2-hidden-accessible + .select2-container .select2-selection__arrow, select[readonly].select2-hidden-accessible + .select2-container .select2-selection__clear {
        display: none;
    }    
</style>

<div class="ps-3">
    <p>
        En este paso puede cargar evaluaciones, exámenes, prácticas que son realizadas antes del diagnóstico. <br>
        <i>Por ejemplo: </i> 
           <h5> <span class="mt-2 badge border border-dark text-dark mt-2">obtención de historia clínica (anamnesis)</span>
            <span class="mt-2 badge border border-dark text-dark mt-2">anamnesis, completa</span>
            <span class="mt-2 badge border border-dark text-dark mt-2">exploración física</span>
            <span class="mt-2 badge border border-dark text-dark mt-2">módulo para diagnóstico</span>
            <span class="mt-2 badge border border-dark text-dark mt-2">exploración física, etc.</span>
           </h5>
    </p>
</div>
<?php $form = ActiveForm::begin(['id' => 'form-practicas-realizadas','options'=>['enctype'=>'multipart/form-data']]); ?>

    <?php
    $min = ($modelosConsultaEvaluaciones[0]->isNewRecord && $modelosConsultaEvaluaciones[0]->codigo_deshabilitado == false) ? 0 : 0;
    DynamicFormWidget::begin([
        'widgetContainer' => 'dynamicform_wrapper', // required: only alphanumeric characters plus "_" [A-Za-z0-9_]
        'widgetBody' => '.container-items_practicas', // required: css class selector
        'widgetItem' => '.item_practicas', // required: css class
        'limit' => 10, // the maximum times, an element can be cloned (default 999)
        'min' => $min, // 0 or 1 (default 1)
        'insertButton' => '.add-item_practicas', // css class
        'deleteButton' => '.remove-item_practicas', // css class
        'model' => $modelosConsultaEvaluaciones[0],
        'formId' => 'form-practicas-realizadas',
        'formFields' => [
            'codigo',
            'archivos_adjuntos'

        ],
    ]);
    ?>
    <div class="container-items_practicas ps-2 pe-2">
        <?php foreach ($modelosConsultaEvaluaciones as $i => $modelEvaluacion) : ?>
            <div class="item_practicas row mb-3 pb-2 pt-3 border rounded border-success border-0 bg-soft-success">
                <div class="col">
                    <div class="row mb-3">
                        <?= $form->errorSummary($modelEvaluacion); ?>
                        <?php if (!$modelEvaluacion->isNewRecord) { ?>
                            <?= $form->field($modelEvaluacion, "[$i]id", ['inputOptions' => ['class' => 'form-control id']])->hiddenInput()->label(false); ?>
                        <?php } ?>

                      
                        <?php $data = !$modelEvaluacion->codigoSnomed ? [] : [$modelEvaluacion->codigo => $modelEvaluacion->codigoSnomed->term]; ?>

                        <div class="col-sm-6" id="select-practica">                            
                            <?php                            
                                $options = ['placeholder' => '- Seleccione una opcion -', 'class' => 'snomed_simple_select2'];
                                $data = !$modelEvaluacion->codigoSnomed ? [] : [$modelEvaluacion->codigo => $modelEvaluacion->codigoSnomed->term];
                            ?>
                            <?=
                            $form->field($modelEvaluacion, "[$i]codigo")->widget(Select2::classname(), [                                
                                'data' => $data,
                                'theme' => 'default',
                                'language' => 'es',
                                'options' => $options,                                
                                'pluginOptions' => [                                    
                                    'minimumInputLength' => 4,
                                    'dropdownParent' => '#select-'.$i.'--practica',
                                    'ajax' => [
                                        'url' => Url::to(['snowstorm/practicas']),
                                        'dataType' => 'json',
                                        'delay'=> 500,
                                        'data' => new JsExpression('function(params) { return {q:params.term}; }'),
                                        'cache' => true
                                    ],
                                    'width' => '80%'
                                ]
                            ])->label(false);
                            ?>
                            <?= Html::hiddenInput(
                                "CustomAttribute[$i][termino_procedimiento]",
                                !$modelEvaluacion->codigoSnomed ? '' : $modelEvaluacion->codigoSnomed->term,
                                ['id' => "consultapracticas-$i-codigo-termino", 'class' => "termino"]
                            );
                            ?>
                        </div>
                    </div>
                   

                    <div class="row mt-5">
                        <div class="col-12">
                            <?= $form->field($modelEvaluacion, "[{$i}]informe")->textArea(['placeholder' => 'Detalle: '])->label(false); ?>
                        </div>
                    </div>

                </div>

                <div class="col col-sm-1 text-center mt-0 ps-0">
                    <a class="float-xl-end remove-item_practicas text-warning" data-bs-toggle="tooltip" data-bs-placement="right" data-bs-original-title="Quitar esta fila">
                        <svg class="icon-32" width="32" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M14.3955 9.59497L9.60352 14.387" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
                            <path d="M14.3971 14.3898L9.60107 9.59277" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
                            <path fill-rule="evenodd" clip-rule="evenodd" d="M16.3345 2.75024H7.66549C4.64449 2.75024 2.75049 4.88924 2.75049 7.91624V16.0842C2.75049 19.1112 4.63549 21.2502 7.66549 21.2502H16.3335C19.3645 21.2502 21.2505 19.1112 21.2505 16.0842V7.91624C21.2505 4.88924 19.3645 2.75024 16.3345 2.75024Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
                        </svg>
                    </a>
                </div>

            </div>
        <?php endforeach; ?>
    </div>

    <div class="row pe-3">
        <div class="col-xs-12">
            <button type="button" class="add-item_practicas mt-2 btn btn-info rounded-pill float-xl-end text-white">
                <i class="bi bi-plus-circle text-white"></i> Detalle Evaluación
            </button>
        </div>
    </div>

    <?php DynamicFormWidget::end(); ?>

    <hr class="border border-info border-1 opacity-50">
    <?php if ($modelConsulta->urlAnterior) { ?>
        <?= Html::a('Anterior', $modelConsulta->urlAnterior, ['class' => 'btn btn-primary atender rounded-pill float-start']) ?>
    <?php } ?>
    <?= Html::submitButton($modelConsulta->urlSiguiente == 'fin' ? 'Finalizar' : 'Guardar y Continuar', ['class' => 'btn btn-primary rounded-pill float-end']) ?>

<?php ActiveForm::end(); ?>

<?php
$headerMenu = $modelConsulta->getHeader();
$header = "$('#modal-consulta-label').html('".$headerMenu."')";
$this->registerJs($header);
?>