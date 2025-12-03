<?php

use yii\helpers\Html;
use yii\widgets\ActiveForm;
use yii\web\JqueryAsset;
use wbraganca\dynamicform\DynamicFormWidget;
use common\models\ConsultaPracticasOftalmologia;
use common\assets\SisseDynamicFormAsset;

SisseDynamicFormAsset::register($this);

/* @var $this yii\web\View */
/* @var $model common\models\ConsultaPracticasOftalmologia */
/* @var $form yii\widgets\ActiveForm */
?>
<style>
    svg {
        fill: none;
    }
</style>
<div class="consulta-practicas-oftalmologia-form">

    <?php $form = ActiveForm::begin(['id' => 'form_oftalmologia']); ?>

    <?php
    DynamicFormWidget::begin([
        'widgetContainer' => 'dynamicform_wrapper', // required: only alphanumeric characters plus "_" [A-Za-z0-9_]
        'widgetBody' => '.container-items', // required: css class selector
        'widgetItem' => '.item', // required: css class
        'limit' => 10, // the maximum times, an element can be cloned (default 999)
        'min' => 0, // 0 or 1 (default 1)
        'insertButton' => '.add-item', // css class
        'deleteButton' => '.remove-item', // css class
        'model' => $oftalmologias[0],
        'formId' => 'form_oftalmologia',
        'formFields' => [
            'ojo',
            'prueba',
            'resultado',
        ],
    ]);
    ?>
    <div class="container-items ps-5 pe-5">
        <?php foreach ($oftalmologias as $i => $model) : ?>
        <div class="item">
            <div class="row">
            <?= $form->errorSummary($model); ?>
                <div class="col-sm-3">
                    <h6>Prueba</h6>
                    <?= $form->field($model, "[{$i}]prueba")->dropDownList([ 'Snellen' => 'Snellen', 'cuenta-dedos' => 'Cuenta-dedos', 'movimiento-manos' => 'Movimiento-manos', 'proyeccion-luminosa' => 'Proyeccion-luminosa', ], ['prompt' => ''])->label(false) ?>
                </div>
                <div class="col-sm-4">
                    <h6>Ojo</h6>
                    <?= $form->field($model, "[{$i}]ojo")->dropDownList(['OD' => 'OD', 'OI' => 'OI', 'AMBOS' => 'AMBOS', ], ['prompt' => ''])->label(false) ?>
                </div>
                <div class="col-sm-3">
                    <h6>Resultado</h6>
                    <?= $form->field($model, "[{$i}]resultado")->textInput(['type' => 'string'])->label(false) ?> <h6> <input type="checkbox" id="no-copera" name="no-copera">  No Copera</h6>
                </div>
                <div class="col-sm-1">
                    <a class="remove-item text-warning"
                       href="#" data-bs-toggle="tooltip"
                       data-bs-placement="right"
                       data-bs-original-title="Quitar esta fila" style="svg: ">
                        <?= $this->render('../site/svg_icon_remove.php'); ?>
                    </a>
                </div>
                <hr class="border border-light border-1 opacity-50">
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <div class="row pe-2">
        <div class="col-xs-12">
            <button type="button" class="add-item mt-1 btn btn-info rounded-pill text-white">
                <i class="bi bi-plus-circle text-white"></i> Agregar Practica Oftalmológica
            </button>
        </div>
    </div>
    <?php DynamicFormWidget::end(); ?>

    <hr class="border border-info border-1 opacity-50">
    <?php if($form_steps): ?>
        <?php if ($modelConsulta->urlAnterior) { ?>
            <?= Html::a(
                'Anterior',
                $modelConsulta->urlAnterior,
                ['class' =>
                    'btn btn-primary atender rounded-pill float-start']) ?>
            <?php
            $headerMenu = $modelConsulta->getHeader();
            $header = "$('#modal-consulta-label').html('".$headerMenu."')";
            $this->registerJs($header);
            ?>
        <?php } ?>
        <?= Html::submitButton(
            $modelConsulta->urlSiguiente == 'fin' ? 'Finalizar' : 'Guardar y Continuar',
            ['class' => 'btn btn-primary rounded-pill float-end']) ?>

    <?php else:?>
        <div class="form-group">
            <?= Html::submitButton('Guardar', ['class' => 'btn btn-success']) ?>
            <?= Html::a(
                'Cancelar',
                ['/consulta-practicas-oftalmologia'],
                ['class' => 'btn btn-danger', 'role' => 'button']) ?>
        </div>
    <?php endif;?>
    <?php ActiveForm::end(); ?>

</div>
