<?php

use yii\helpers\Html;
use yii\widgets\ActiveForm;

/* @var $this yii\web\View */
/* @var $model common\models\busquedas\ConsultaPracticasOftalmologiaBusqueda */
/* @var $form yii\widgets\ActiveForm */
?>

<div class="consulta-practicas-oftalmologia-search">

    <?php $form = ActiveForm::begin([
        'action' => ['index'],
        'method' => 'get',
    ]); ?>

    <?= $form->field($model, 'id') ?>

    <?= $form->field($model, 'id_consulta') ?>

    <?= $form->field($model, 'codigo') ?>

    <?= $form->field($model, 'ojo') ?>

    <?= $form->field($model, 'prueba') ?>

    <?php // echo $form->field($model, 'estado') ?>

    <?php // echo $form->field($model, 'resultado') ?>

    <?php // echo $form->field($model, 'informe') ?>

    <?php // echo $form->field($model, 'adjunto') ?>

    <div class="form-group">
        <?= Html::submitButton('Search', ['class' => 'btn btn-primary']) ?>
        <?= Html::resetButton('Reset', ['class' => 'btn btn-outline-secondary']) ?>
    </div>

    <?php ActiveForm::end(); ?>

</div>
