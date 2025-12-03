<?php

use yii\helpers\Url;
use yii\helpers\Html;
use yii\web\JsExpression;
use yii\bootstrap5\ActiveForm;

use kartik\date\DatePicker;
use kartik\select2\Select2;
use kartik\file\FileInput;

use common\models\DocumentosExternos;

use common\models\Adjunto;

use \frontend\controllers\traits\AdjuntoTrait;

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

<?php $form = ActiveForm::begin(['id' => 'form-documentos-externos']); ?>


    <div class="mb-3 pb-2 pt-3">
        <div class="row mb-3">
            <?= $form->errorSummary($modelDocumentoExterno); ?>
            <?php if (!$modelDocumentoExterno->isNewRecord) { ?>
                <?= $form->field($modelDocumentoExterno, "id", ['inputOptions' => ['class' => 'form-control id']])->hiddenInput()->label(false); ?>
            <?php } ?>
            
            <div class="col-4">
                <h6>Título</h6>  
                <?= $form->field($modelDocumentoExterno, "titulo")->input(['placeholder' => 'Título'])->label(false); ?>
            </div>                        

            <div class="col-4">
                <h6>Tipo</h6>                            
                <?= $form->field($modelDocumentoExterno, "tipo", ['options' => ['class' => 'mb-0']])
                        ->dropDownList(DocumentosExternos::TIPOS, ['prompt' => '- Seleccione el tipo -'])
                        ->label(false) 
                ?>
            </div>

            <div class="col-4">
                <h6>Fecha</h6>                            
                <?= $form->field($modelDocumentoExterno, 'fecha')->widget(DatePicker::className(), [
                    'type' => DatePicker::TYPE_COMPONENT_APPEND,
                    'pickerIcon' => '<i class="bi bi-calendar2-week"></i>',
                    'removeIcon' => '<i class="bi bi-trash"></i>',
                    'pluginOptions' => [
                        'autoclose' => true
                    ]
                ])->label(false); ?>
            </div>                        
        </div>

        <div class="row">

            <div class="col-12">

                <?php list($archivos, $previewInicial) = AdjuntoTrait::cargarArchivos('DocumentosExternos', $modelDocumentoExterno->id); ?>

                <?= $form->field($modelDocumentoExterno, "archivos_adjuntos[]")->widget(FileInput::className(), [
                    'options' => ['multiple' => true],
                    'pluginOptions' => [
                        'initialPreview' => $archivos,
                        'initialPreviewAsData' => true,
                        "initialPreviewConfig" => $previewInicial,
                        'overwriteInitial' => false,
                        'fileActionSettings' => [
                            'showZoom' => true,
                            'showRemove' => true,
                            'showRotate' => false,
                        ],
                        'showCancel' => false,
                        'showUpload' => false,
                        'browseClass' => 'btn btn-soft-primary',
                        'removeClass' => 'btn btn-soft-danger',
                        'removeIcon' => '<i class="bi bi-trash"></i>',

                    ],
                    'pluginEvents' => [
                        'filepredelete' => 'function(jqXHR) {
                                var abort = true;
                                if (confirm("Esta seguro que desea borrar este archivo?")) {
                                    abort = false;
                                }
                                return abort
                            }'

                    ]

                ])->label(false); ?>

            </div>

        </div>
    </div>

    <hr class="border border-info border-1 opacity-50">

    <?= Html::submitButton('Guardar', ['class' => 'btn btn-primary rounded-pill float-end']) ?>

<?php ActiveForm::end(); ?>