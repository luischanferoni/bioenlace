<?php

use yii\helpers\Html;
use yii\widgets\ActiveForm;

/* @var $this yii\web\View */
/* @var $model common\models\Profesiones */
/* @var $form yii\widgets\ActiveForm */
?>

<div class="profesiones-form">

    <?php $form = ActiveForm::begin(); ?>

   <?= $form->field($model, 'nombre')->textInput() ?>
  
    
 <div class="form-group">
        <?= Html::submitButton($model->isNewRecord ? 'Nuevo' : 'Actualizar', ['class' => $model->isNewRecord ? 'btn btn-success' : 'btn btn-primary']) ?>
    </div>
    
    <?php ActiveForm::end(); ?>

</div>

