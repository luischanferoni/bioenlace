<?php

use yii\helpers\Html;
use yii\widgets\ActiveForm;

/* @var $this yii\web\View */
/* @var $model common\models\busquedas\ConsultasRecetaLentesBusqueda */
/* @var $form yii\widgets\ActiveForm */
?>

<div class="consultas-receta-lentes-search">

    <?php $form = ActiveForm::begin([
        'action' => ['index'],
        'method' => 'get',
    ]); ?>

    <?= $form->field($model, 'id') ?>

    <?= $form->field($model, 'oi_esfera') ?>

    <?= $form->field($model, 'od_esfera') ?>

    <?= $form->field($model, 'oi_cilindro') ?>

    <?= $form->field($model, 'od_cilindro') ?>

    <?php // echo $form->field($model, 'oi_eje') ?>

    <?php // echo $form->field($model, 'od_eje') ?>

    <div class="form-group">
        <?= Html::submitButton('Search', ['class' => 'btn btn-primary']) ?>
        <?= Html::resetButton('Reset', ['class' => 'btn btn-outline-secondary']) ?>
    </div>

    <?php ActiveForm::end(); ?>

</div>
