<?php

use yii\helpers\Html;
use yii\widgets\ActiveForm;

/* @var $this yii\web\View */
/* @var $model common\models\busquedas\ReferenciaBusqueda */
/* @var $form yii\widgets\ActiveForm */
?>

<div class="referencia-search">

    <?php $form = ActiveForm::begin([
        'action' => ['index'],
        'method' => 'get',
    ]); ?>

    <?= $form->field($model, 'id_referencia') ?>

    <?= $form->field($model, 'id_consulta') ?>

    <?= $form->field($model, 'id_efector_referenciado') ?>

    <?= $form->field($model, 'id_motivo_derivacion') ?>

    <?= $form->field($model, 'id_servicio') ?>

    <?php // echo $form->field($model, 'estudios_complementarios') ?>

    <?php // echo $form->field($model, 'resumen_hc') ?>

    <?php // echo $form->field($model, 'tratamiento_previo') ?>

    <?php // echo $form->field($model, 'tratamiento') ?>

    <?php // echo $form->field($model, 'id_estado') ?>

    <?php // echo $form->field($model, 'fecha_turno') ?>

    <?php // echo $form->field($model, 'hora_turno') ?>

    <?php // echo $form->field($model, 'observacion') ?>

    <div class="form-group">
        <?= Html::submitButton('Search', ['class' => 'btn btn-primary']) ?>
        <?= Html::resetButton('Reset', ['class' => 'btn btn-default']) ?>
    </div>

    <?php ActiveForm::end(); ?>

</div>
