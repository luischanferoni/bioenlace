<?php

use yii\helpers\Html;
use yii\widgets\ActiveForm;

/* @var $this yii\web\View */
/* @var $model common\models\Recibidos */
/* @var $form yii\widgets\ActiveForm */

?>

<div class="recibidos-form">

    <?php $form = ActiveForm::begin(); ?>

    <?= $form->field($model, 'id_emisor')->textInput() ?>

    <?= $form->field($model, 'id_receptor')->textInput() ?>

    <?= $form->field($model, 'texto')->textarea(['rows' => 6]) ?>

    <?= $form->field($model, 'fecha')->textInput() ?>
    
     
      
    <div class="form-group">
        <?= Html::submitButton($model->isNewRecord ? 'Nuevo' : 'Actualizar', ['class' => $model->isNewRecord ? 'btn btn-success' : 'btn btn-primary']) ?>
    </div>

    <?php ActiveForm::end(); ?>

</div>
