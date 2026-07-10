<?php

use common\models\BillingAccount;
use yii\helpers\Html;
use yii\widgets\ActiveForm;

/* @var $this yii\web\View */
/* @var $model common\models\BillingAccount */

$form = ActiveForm::begin();
?>
<?= $form->field($model, 'nombre')->textInput(['maxlength' => true]) ?>
<?= $form->field($model, 'tipo')->dropDownList(BillingAccount::tipoOptions()) ?>
<?= $form->field($model, 'notas')->textarea(['rows' => 3]) ?>
<?= $form->field($model, 'activo')->dropDownList([1 => 'Activo', 0 => 'Inactivo']) ?>
<div class="form-group">
    <?= Html::submitButton('Guardar', ['class' => 'btn btn-primary']) ?>
    <?= Html::a('Cancelar', $model->isNewRecord ? ['index'] : ['view', 'id' => $model->id], ['class' => 'btn btn-secondary']) ?>
</div>
<?php ActiveForm::end(); ?>
