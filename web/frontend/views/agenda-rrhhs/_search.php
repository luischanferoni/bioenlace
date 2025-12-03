<?php

use yii\helpers\Html;
use yii\widgets\ActiveForm;

/* @var $this yii\web\View */
/* @var $model common\models\busquedas\Agenda_rrhhBusqueda */
/* @var $form yii\widgets\ActiveForm */
?>

<div class="agenda-rrhh-search">

    <?php $form = ActiveForm::begin([
        'action' => ['index'],
        'method' => 'get',
    ]); ?>

    <?= $form->field($model, 'id_agenda_rrhh') ?>

    <?= $form->field($model, 'id_rr_hh') ?>

    <?= $form->field($model, 'hora_inicio') ?>

    <?= $form->field($model, 'hora_fin') ?>

    <?= $form->field($model, 'lunes') ?>

    <?php // echo $form->field($model, 'martes') ?>

    <?php // echo $form->field($model, 'miercoles') ?>

    <?php // echo $form->field($model, 'jueves') ?>

    <?php // echo $form->field($model, 'viernes') ?>

    <?php // echo $form->field($model, 'sabado') ?>

    <?php // echo $form->field($model, 'domingo') ?>

    <?php // echo $form->field($model, 'id_tipo_dia') ?>

    <?php // echo $form->field($model, 'fecha_inicio') ?>

    <?php // echo $form->field($model, 'fecha_fin') ?>

    <?php // echo $form->field($model, 'id_efector') ?>

    <div class="form-group">
        <?= Html::submitButton('Search', ['class' => 'btn btn-primary']) ?>
        <?= Html::resetButton('Reset', ['class' => 'btn btn-default']) ?>
    </div>

    <?php ActiveForm::end(); ?>

</div>
