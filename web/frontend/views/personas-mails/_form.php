<?php

use yii\helpers\Html;
use yii\widgets\ActiveForm;

/* @var $this yii\web\View */
/* @var $model app\models\persona_mails */
/* @var $form yii\widgets\ActiveForm */
extract($_GET);

?>

<div class="persona-mails-form">

    <?php $form = ActiveForm::begin(); ?>

    <?php //echo $form->field($model, 'id_persona')->textInput() ?>
    <?php echo $form->field($model, 'id_persona',
      ['options' => ['value'=> $idp] ])->hiddenInput()->label(false); ?>
    
    
    <?= $form->field($model, 'mail')->textInput(['maxlength' => true]) ?>

    <div class="form-group">
        <?= Html::submitButton($model->isNewRecord ? 'Guardar' : 'Actualizar', ['class' => $model->isNewRecord ? 'btn btn-success' : 'btn btn-primary']) ?>
    </div>

    <?php ActiveForm::end(); ?>

</div>
