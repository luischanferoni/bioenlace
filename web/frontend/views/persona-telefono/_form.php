<?php

use common\models\Tipo_telefono;
use yii\helpers\Html;
use yii\bootstrap5\ActiveForm;

/* @var $this yii\web\View */
/* @var $model app\models\persona_telefono */
/* @var $form yii\widgets\ActiveForm */

extract($_GET);

?>

<div class="persona-telefono-form">

  <?php $form = ActiveForm::begin(); ?>

  <?php echo $form->field(
    $model,
    'id_persona',
    ['options' => ['value' => $idp]]
  )->hiddenInput()->label(false); ?>

  <?php //echo  $form->field($model, 'id_tipo_telefono')->textInput(['maxlength' => true]) 
  ?>
  <?php
  echo $form->field($model, 'id_tipo_telefono', [
    'template' => '{input}{error}{hint}'
  ])->dropDownList(
    Tipo_telefono::getListaTiposTelefono(),
    ['prompt' => ' -- Elija una opcion --']
  );

  ?>
  <?= $form->field($model, 'numero')->textInput(['maxlength' => true]) ?>

  <?= $form->field($model, 'comentario')->textarea(['rows' => 6]) ?>
</div>

<div class="form-group">
  <?= Html::submitButton($model->isNewRecord ? 'Guardar' : 'Actualizar', ['class' => $model->isNewRecord ? 'btn btn-success' : 'btn btn-primary']) ?>
</div>

<?php ActiveForm::end(); ?>

</div>