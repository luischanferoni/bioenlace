<?php

use yii\helpers\Html;
use yii\widgets\ActiveForm;

/* @var $this yii\web\View */
/* @var $model common\models\Servicio */
/* @var $form yii\widgets\ActiveForm */
?>

<div class="servicio-form">

    <?php $form = ActiveForm::begin(); ?>
	
    <div class="form-group">
        <?= $form->field($model, 'nombre')->textInput(['maxlength' => true]); ?>
        
        <?= $form->field($model, 'acepta_turnos')->dropDownList([ 'SI' => 'SI', 'NO' => 'NO',]) ?>
        <?= $form->field($model, 'acepta_practicas')->dropDownList([ 'SI' => 'SI', 'NO' => 'NO',]) ?>

        <?= $form->field($model, 'hallazgos_ecl')->textArea(); ?>
        <?= $form->field($model, 'medicamentos_ecl')->textarea(); ?>
        <?= $form->field($model, 'procedimientos_ecl')->textarea(); ?>

        <label class="control-label" for="color">Color</label>
		<?= Html::input('color', 'color', $model->parametros['color'], ['id' => 'color']) ?>

	</div>
    <div class="form-group">
        <?= Html::submitButton($model->isNewRecord ? 'Nuevo' : 'Actualizar', ['class' => $model->isNewRecord ? 'btn btn-success' : 'btn btn-primary']) ?>
    </div>

    <?php ActiveForm::end(); ?>

</div>
