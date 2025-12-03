<?php

use yii\helpers\Html;
use yii\widgets\ActiveForm;

/* @var $this yii\web\View */
/* @var $model common\models\busquedas\SegNivelInternacionPracticaBusqueda */
/* @var $form yii\widgets\ActiveForm */
?>

<div class="seg-nivel-internacion-practica-search">

    <?php $form = ActiveForm::begin([
        'action' => ['index'],
        'method' => 'get',
    ]); ?>

    <?= $form->field($model, 'id') ?>

    <?= $form->field($model, 'conceptId') ?>

    <?= $form->field($model, 'resultado') ?>

    <?= $form->field($model, 'informe') ?>

    <?= $form->field($model, 'id_rrhh_solicita') ?>

    <?php // echo $form->field($model, 'id_rrhh_realiza') ?>

    <?php // echo $form->field($model, 'id_internacion') ?>

    <div class="form-group">
        <?= Html::submitButton('Search', ['class' => 'btn btn-primary']) ?>
        <?= Html::resetButton('Reset', ['class' => 'btn btn-outline-secondary']) ?>
    </div>

    <?php ActiveForm::end(); ?>

</div>
