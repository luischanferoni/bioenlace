<?php

use yii\helpers\Html;
use yii\widgets\ActiveForm;

/* @var $this yii\web\View */
/* @var $model common\models\PersonaPrograma */
/* @var $form yii\widgets\ActiveForm */
?>

<div class="persona-programa-form">

    <?php $form = ActiveForm::begin(); ?>

    <?php /* <?= $form->field($model, 'clave_beneficiario')->textInput(['maxlength' => true]) ?> */ ?>

    <?= $form->field($model, 'tipo_empadronamiento')->dropDownList(['ALTA' => 'ALTA', 'REEMPADRONAMIENTO' => 'REEMPADRONAMIENTO', 'RENOVACION' => 'RENOVACION',], ['prompt' => '']) ?>

    <?= $form->field($model, 'fecha')->widget(\yii\jui\DatePicker::className()) ?>

    <?php if ($personaEmpadronada) {

        echo $form->field($model, 'fecha_baja')->widget(\yii\jui\DatePicker::className());

        echo $form->field($model, 'motivo_baja')->textInput(['maxlength' => true]);
    } ?>


    <div class="form-group">
        <?= Html::submitButton('Siguiente', ['class' => 'btn btn-success']) ?>
    </div>

    <?php ActiveForm::end(); ?>

</div>