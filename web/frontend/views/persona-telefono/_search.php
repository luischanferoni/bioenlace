<?php

use yii\helpers\Html;
use yii\widgets\ActiveForm;

/* @var $this yii\web\View */
/* @var $model app\models\busquedas\Persona_telefonoBusqueda */
/* @var $form yii\widgets\ActiveForm */
?>

<div class="persona-telefono-search">

    <?php $form = ActiveForm::begin([
        'action' => ['index'],
        'method' => 'get',
    ]); ?>

    <?= $form->field($model, 'id_persona_telefono') ?>

    <?= $form->field($model, 'id_persona') ?>

    <?= $form->field($model, 'id_tipo_telefono') ?>

    <?= $form->field($model, 'numero') ?>

    <?= $form->field($model, 'comentario') ?>

    <div class="form-group">
        <?= Html::submitButton('Search', ['class' => 'btn btn-primary']) ?>
        <?= Html::resetButton('Reset', ['class' => 'btn btn-default']) ?>
    </div>

    <?php ActiveForm::end(); ?>

</div>
