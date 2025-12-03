<?php

use yii\helpers\Html;
use yii\widgets\ActiveForm;
use yii\helpers\ArrayHelper;
use kartik\select2\Select2;


/* @var $this yii\web\View */
/* @var $model common\models\SegNivelInternacionHcama */
/* @var $form yii\widgets\ActiveForm */
?>

<div class="seg-nivel-internacion-hcama-form">

    <?php $form = ActiveForm::begin(); ?>
    
    <?= $form->errorSummary($model); ?>
    <div class="form-group">
        <label class="control-label">
        Paciente
        </label>
        <input type="text" class="form-control" readonly 
               value="<?= ArrayHelper::getValue($context, 'paciente')?>"/>
    </div>
    <div class="form-group">
        <label class="control-label">
        Cama Actual
        </label>
        <input type="text" class="form-control" readonly 
               value="<?= ArrayHelper::getValue($context, 'cama_actual') ?>"/>
    </div>
    
    <?= '' #$form->field($model, 'id_internacion')->textInput() ?>
    <?= $form->field($model, 'id_cama')->widget(Select2::classname(), [
        'data' => ArrayHelper::map($context['camas'], 'code', 'label'),
        'theme' => Select2::THEME_DEFAULT,
        'language' => 'en',
        'options' => [
            'placeholder' => 'Seleccione cama.',
        ],
        'pluginOptions' => [
            'allowClear' => true,
            'width' => '100%'
            ]
        ])
        //->label('Nueva cama')
        ;?>
    <?= $form->field($model, 'motivo')->textarea([
        'rows' => 6,
        'maxlength' => true
        ]) ?>

    <div class="form-group">
        <?= Html::submitButton('Guardar', ['class' => 'btn btn-success']) ?>
        <?= Html::a('Cancelar',
                null, [
                    'class' => 'btn btn-danger',
                    'onClick' => 'history.back();']
                ) ?>
    </div>

    <?php ActiveForm::end(); ?>

</div>

