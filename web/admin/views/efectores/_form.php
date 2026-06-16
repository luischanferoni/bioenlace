<?php

use yii\helpers\Html;
use yii\widgets\ActiveForm;

/* @var $this yii\web\View */
/* @var $model common\models\Efector */
/* @var $form yii\widgets\ActiveForm */
?>

<div class="efector-form">

    <?php $form = ActiveForm::begin(); ?>

    <?= $form->field($model, 'codigo_sisa')->textInput(['maxlength' => true,'readonly' =>true ]) ?>

    <?= $form->field($model, 'nombre')->textInput(['maxlength' => true,'readonly' =>true]) ?>

    <?= $form->field($model, 'dependencia')->textInput(['maxlength' => true,'readonly' =>true]) ?>

    <?= $form->field($model, 'tipologia')->textInput(['maxlength' => true,'readonly' =>true]) ?>

    <?= $form->field($model, 'domicilio')->textInput(['maxlength' => true]) ?>

    <?= $form->field($model, 'grupo')->textInput(['maxlength' => true]) ?>

    <?= $form->field($model, 'formas_acceso')->textInput(['maxlength' => true]) ?>

    <?= $form->field($model, 'telefono')->textInput(['maxlength' => true]) ?>

    <?= $form->field($model, 'telefono2')->textInput(['maxlength' => true]) ?>

    <?= $form->field($model, 'telefono3')->textInput(['maxlength' => true]) ?>

    <?= $form->field($model, 'mail1')->textInput(['maxlength' => true]) ?>

    <?= $form->field($model, 'mail2')->textInput(['maxlength' => true]) ?>

    <?= $form->field($model, 'mail3')->textInput(['maxlength' => true]) ?>

    <?= $form->field($model, 'dias_horario')->textInput(['maxlength' => true]) ?>

    <?= $form->field($model, 'origen_financiamiento')->textInput(['maxlength' => true,'readonly' =>true]) ?>

    <?= $form->field($model, 'id_localidad')->textInput(['readonly' =>true]) ?>

    <?= $form->field($model, 'estado')->dropDownList([ 'ACTIVO' => 'ACTIVO', 'INACTIVO' => 'INACTIVO', ], ['prompt' => '','readonly' =>true]) ?>

    <div class="form-group">
        <?= Html::submitButton($model->isNewRecord ? 'Guardar' : 'Modificar', ['class' => $model->isNewRecord ? 'btn btn-success' : 'btn btn-primary']) ?>
    </div>

    <?php ActiveForm::end(); ?>

</div>
