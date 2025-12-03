<?php

use yii\helpers\Html;
use yii\widgets\ActiveForm;

/* @var $this yii\web\View */
/* @var $model common\models\busquedas\SegNivelInternacionAtencionesEnfermeriaBusqueda */
/* @var $form yii\widgets\ActiveForm */
?>

<div class="seg-nivel-internacion-atenciones-enfermeria-search">

    <?php $form = ActiveForm::begin([
        'action' => ['index'],
        'method' => 'get',
    ]); ?>

    <?= $form->field($model, 'id') ?>

    <?= $form->field($model, 'datos') ?>

    <?= $form->field($model, 'observaciones') ?>

    <?= $form->field($model, 'id_internacion') ?>

    <?= $form->field($model, 'id_user') ?>

    <?php // echo $form->field($model, 'fecha_creacion') ?>

    <div class="form-group">
        <?= Html::submitButton('Search', ['class' => 'btn btn-primary']) ?>
        <?= Html::resetButton('Reset', ['class' => 'btn btn-outline-secondary']) ?>
    </div>

    <?php ActiveForm::end(); ?>

</div>
