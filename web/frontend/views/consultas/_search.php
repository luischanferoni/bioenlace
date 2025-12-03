<?php

use yii\helpers\Html;
use yii\widgets\ActiveForm;
?>

<div class="consulta-search">

    <?php $form = ActiveForm::begin([
        'action' => ['index'],
        'method' => 'get',
    ]); ?>

    <?= $form->field($model, 'id_consulta') ?>

    <?= $form->field($model, 'id_turnos') ?>

    <?= $form->field($model, 'hora') ?>

    <?= $form->field($model, 'consulta_inicial') ?>

    <?= $form->field($model, 'id_tipo_ingreso') ?>

    <?php // echo $form->field($model, 'motivo_consulta') ?>

    <?php // echo $form->field($model, 'observacion') ?>

    <?php // echo $form->field($model, 'control_embarazo') ?>

    <div class="form-group">
        <?= Html::submitButton('Search', ['class' => 'btn btn-primary']) ?>
        <?= Html::resetButton('Reset', ['class' => 'btn btn-default']) ?>
    </div>

    <?php ActiveForm::end(); ?>

</div>
