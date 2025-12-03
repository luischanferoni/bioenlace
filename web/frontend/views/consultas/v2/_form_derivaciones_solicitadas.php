<?php

use yii\helpers\Url;
use yii\helpers\Html;
use yii\web\JsExpression;
use yii\bootstrap5\ActiveForm;

use kartik\select2\Select2;
use yii\bootstrap\Dropdown;
use wbraganca\dynamicform\DynamicFormWidget;

use common\assets\SisseDynamicFormAsset;
use kartik\depdrop\DepDrop;

SisseDynamicFormAsset::register($this);

?>

<?php $form = ActiveForm::begin(['id' => 'form-derivaciones-solicitadas']); ?>


    <?php
    DynamicFormWidget::begin([
        'widgetContainer' => 'dynamicform_wrapper', // required: only alphanumeric characters plus "_" [A-Za-z0-9_]
        'widgetBody' => '.container-items_derivacion_solicitada', // required: css class selector
        'widgetItem' => '.item_derivacion_solicitada', // required: css class
        'limit' => 4, // the maximum times, an element can be cloned (default 999)
        'min' => 0, // 0 or 1 (default 1)
        'insertButton' => '.add-item_derivacion_solicitada', // css class
        'deleteButton' => '.remove-item_derivacion_solicitada', // css class
        'model' => $modelosConsultaDerivacionesSolicitadas[0],
        'formId' => 'form-derivaciones-solicitadas',
        'formFields' => [
            'codigo',
        ],
    ]);
    ?>

    <div class="container-items_derivacion_solicitada ps-2 pe-2">

        <?php foreach ($modelosConsultaDerivacionesSolicitadas as $i => $modelDerivacionSolicitada) : ?>

            <div class="item_derivacion_solicitada row pt-3 mb-3 border rounded border-success border-0 bg-soft-success">
                <div class="col-sm-11 col-xs-12">
                    <div class="row">
                        <?php if($modelDerivacionSolicitada->estado == 'RECHAZADA'){?>
                            <div class="alert alert-warning notificacion" id="notificacion" role="alert">Esta Practica fue rechazada por el Efector seleccionado, la misma puede ser modificada o cancelada.</div>
                        <?php }?>
                        <div class="col-md-4 mt-3">
                            <?= $form->field($modelDerivacionSolicitada, "[{$i}]tipo_solicitud", 
                                ['labelOptions' => ['class' => 'form-label me-2']])
                                ->dropDownList(
                                [
                                    "PRACTICA" => "PRACTICA",
                                    "INTERCONSULTA" => "INTERCONSULTA"
                                ],
                                ['class' => 'form-select d-inline', 'style' => 'width: 75%']
                            );
                            ?>
                        </div>
                        <div class="col-7 mt-3" id="select-solicitud">
                            <?php if (!$modelDerivacionSolicitada->isNewRecord) { ?>
                                <?= $form->field($modelDerivacionSolicitada, "[$i]id", ['inputOptions' => ['class' => 'form-control id']])->hiddenInput()->label(false); ?>
                            <?php } ?>

                            <?php $data = !$modelDerivacionSolicitada->codigoSnomed ? [] : [$modelDerivacionSolicitada->codigo => $modelDerivacionSolicitada->codigoSnomed->term]; ?>

                            <?=
                            $form->field($modelDerivacionSolicitada, "[$i]codigo", ['labelOptions' => ['class' => 'form-label d-block']])->widget(Select2::classname(), [
                                'data' => $data,
                                'theme' => 'default',
                                'options' => ['placeholder' => '- Escriba la práctica -', 'class' => 'snomed_simple_select2'],
                                'pluginOptions' => [
                                    'width' => '70%',
                                    'minimumInputLength' => 4,
                                    'dropdownParent' => '#select-'.$i.'--solicitud',
                                    'ajax' => [
                                        'url' => Url::to(['snowstorm/practicas']),
                                        'dataType' => 'json',
                                        'delay'=> 500,
                                        'data' => new JsExpression('function(params) { return {q:params.term}; }'),
                                        'cache' => true
                                    ],
                                ],
                            ])->label('Práctica')
                            ?>
                            <?php // El attributo id es el mismo que el select2 + -termino 
                            ?>
                            <?= Html::hiddenInput(
                                "CustomAttribute[$i][termino_procedimiento]",
                                !$modelDerivacionSolicitada->codigoSnomed ? '' : $modelDerivacionSolicitada->codigoSnomed->term,
                                ['id' => "consultaderivaciones-$i-codigo-termino", 'class' => "termino"]
                            );
                            ?>
                        </div>

                        <div class="col-md-6">
                            <?= $form->field($modelDerivacionSolicitada, "[$i]id_servicio")->widget(Select2::className(), [
                                'data' => $serviciosAceptaPracticas,
                                'theme' => 'default',
                                'options' => ['placeholder' => 'Seleccione un servicio'],
                                'pluginOptions' => ['dropdownParent' => '#modal-consulta', 'width' => '80%']
                            ]) ?>
                        </div>

                        <?php $data = !$modelDerivacionSolicitada->isNewRecord ? [$modelDerivacionSolicitada->id_efector => $modelDerivacionSolicitada->efector->nombre] : ''; ?>

                        <div class="col-md-6">
                            <?= $form->field($modelDerivacionSolicitada, "[$i]id_efector")->widget(DepDrop::className(), [
                                'data' => $data,
                                'options' => ['id' => 'consultaderivaciones-' . $i . '-id_efector'],
                                'type' => DepDrop::TYPE_SELECT2,
                                'select2Options' => ['theme' => 'default', 'pluginOptions' => ['width' => '80%', 'dropdownParent' => '#modal-consulta']],
                                'pluginOptions' => [
                                    'depends' => ['consultaderivaciones-' . $i . '-id_servicio'],
                                    'initialize' => $modelDerivacionSolicitada->isNewRecord ? false : true,
                                    'placeholder' => 'Seleccione un efector',
                                    'url' => Url::to(['/servicios-efectores/efectores-por-servicio'])
                                ]

                            ]) ?>
                        </div>

                        <div class="col-md-10">
                            <?= $form->field($modelDerivacionSolicitada, "[{$i}]indicaciones")->textArea(['placeholder' => 'Indicaciones']) ?>
                        </div>
                    </div>
                </div>
                <div class="col-sm-1 col-xs-12 text-center">
                    <a class="float-xl-end remove-item_derivacion_solicitada text-warning" hidden="hidden" href="#" data-bs-toggle="tooltip" data-bs-placement="right" data-bs-original-title="Quitar esta fila">
                        <svg class="icon-32" width="32" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M14.3955 9.59497L9.60352 14.387" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
                            <path d="M14.3971 14.3898L9.60107 9.59277" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
                            <path fill-rule="evenodd" clip-rule="evenodd" d="M16.3345 2.75024H7.66549C4.64449 2.75024 2.75049 4.88924 2.75049 7.91624V16.0842C2.75049 19.1112 4.63549 21.2502 7.66549 21.2502H16.3335C19.3645 21.2502 21.2505 19.1112 21.2505 16.0842V7.91624C21.2505 4.88924 19.3645 2.75024 16.3345 2.75024Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
                        </svg>
                    </a>
                </div>
                <hr class="border border-light border-1 opacity-50">
            </div>                
        <?php endforeach; ?>
    </div>
    <div class="row">
        <div class="col-xs-12">
            <button type="button" class="add-item_derivacion_solicitada mt-2 btn btn-info rounded-pill float-xl-end text-white">
                <i class="bi bi-plus-circle text-white"></i> Agregar Solicitud
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

$script = <<<JS
$(".dynamicform_wrapper").on("afterInsert", function(e, item) { 
    $(item).find('.notificacion').hide();
    $(item).find('.remove-item_derivacion_solicitada').removeAttr('hidden');
});
JS;
$this->registerJs($script);
?>