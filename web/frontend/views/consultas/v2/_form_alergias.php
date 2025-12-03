<?php

use yii\helpers\ArrayHelper;
use yii\helpers\Url;
use yii\helpers\Html;
use yii\web\JsExpression;
use yii\bootstrap5\ActiveForm;

use kartik\select2\Select2;
use wbraganca\dynamicform\DynamicFormWidget;

use common\assets\SisseDynamicFormAsset;
use common\models\Cie10;
use common\models\Consulta;
use common\models\Alergias;

SisseDynamicFormAsset::register($this);

?>

<?php $form = ActiveForm::begin(['id' => 'form-alergias']); ?>

    <?php
    DynamicFormWidget::begin([
        'widgetContainer' => 'dynamicform_wrapper', // required: only alphanumeric characters plus "_" [A-Za-z0-9_]
        'widgetBody' => '.container-items_alergias', // required: css class selector
        'widgetItem' => '.item_alergias', // required: css class
        'limit' => 10, // the maximum times, an element can be cloned (default 999)
        'min' => 0, // 0 or 1 (default 1)
        'insertButton' => '.add-item_alergias', // css class
        'deleteButton' => '.remove-item_alergias', // css class
        'model' => $model_alergias[0],
        'formId' => 'form-alergias',
        'formFields' => [
            'id_snomed_hallazgo',
            'tipo',
            'categoria',
            'criticidad',
        ],
    ]);
    ?>

    <div class="container-items_alergias ps-5 pe-5">
        <?php foreach ($model_alergias as $i => $model_aler) : ?>
            <div class="item_alergias row mb-3 pb-2 pt-3 border rounded border-success border-0 bg-soft-success">
                <div class="col-sm-4" id="select-alergia">

                    <?php if (!$model_aler->isNewRecord) { ?>
                        <?= $form->field($model_aler, "[$i]id", ['options' => ['class' => 'mb-0'], 'inputOptions' => ['class' => 'form-control id']])->hiddenInput()->label(false); ?>
                    <?php } ?>

                    <?php $data = !$model_aler->codigoSnomed ? [] : [$model_aler->id_snomed_hallazgo => $model_aler->codigoSnomed->term]; ?>
                    <?=
                    $form->field($model_aler, "[{$i}]id_snomed_hallazgo")->widget(Select2::classname(), [
                        'data' => $data,
                        'theme' => 'default',
                        'options' => ['placeholder' => '-Seleccione la alergia/intolerancia-', 'class' => 'snomed_simple_select2'],
                        'pluginOptions' => [
                            'minimumInputLength' => 4,
                            'dropdownParent' => '#select-'.$i.'--alergia',
                            'width' => '100%',
                            'ajax' => [
                                'url' => Url::to(['snowstorm/alergias']),
                                'dataType' => 'json',
                                'delay'=> 500,
                                'data' => new JsExpression('function(params) { return {q:params.term}; }'),
                                'cache' => true,
                            ],
                        ],
                    ])->label(false);
                    ?>
                    <?php // El attributo id es el mismo que el select2 + -termino 
                    ?>
                    <?= Html::hiddenInput(
                        "CustomAttribute[$i][termino_hallazgo]",
                        !$model_aler->codigoSnomed ? '' : $model_aler->codigoSnomed->term,
                        ['id' => "alergias-$i-id_snomed_hallazgo-termino", 'class' => "termino"]
                    );
                    ?>
                </div>
                <div class="col-2">
                    <?= $form->field($model_aler, "[{$i}]tipo")->DropDownList(Alergias::TIPOS, ['prompt' => '- Tipo -'])->label(FALSE) ?>
                </div>

                <div class="col-2">
                    <?= $form->field($model_aler, "[{$i}]categoria")->DropDownList(Alergias::CATEGORIAS, ['prompt' => '- Categoría -'])->label(FALSE) ?>
                </div>
                <div class="col-2">
                    <?= $form->field($model_aler, "[{$i}]criticidad")->DropDownList(Alergias::CRITICIDADES, ['prompt' => '- Criticidad -'])->label(FALSE) ?>
                </div>
                <div class="col-sm-2 col text-center ">
                    <a class="float-xl-end remove-item_alergias text-warning" data-bs-toggle="tooltip" data-bs-placement="right" data-bs-original-title="Quitar esta fila">
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
    <div class="row pe-2">
        <div class="col-xs-12">
            <button type="button" class="add-item_alergias mt-2 btn btn-info rounded-pill float-xl-end text-white">
                <i class="bi bi-plus-circle text-white"></i> Agregar Alergia/Intolerancia
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