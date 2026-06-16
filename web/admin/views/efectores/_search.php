<?php

use yii\helpers\Html;
use yii\widgets\ActiveForm;

/* @var $this yii\web\View */
/* @var $model common\models\busquedas\EfectorBusqueda */
/* @var $form yii\widgets\ActiveForm */
?>

<div class="efector-search">

    <?php $form = ActiveForm::begin([
        'action' => ['index'],
        'method' => 'get',
    ]); ?>

    <?= $form->field($model, 'id_efector') ?>

    <?= $form->field($model, 'codigo_sisa') ?>

    <?= $form->field($model, 'nombre') ?>

    <?= $form->field($model, 'dependencia') ?>

    <?= $form->field($model, 'tipologia') ?>
     
    <?php // echo $form->field($model, 'domicilio') ?>

    <?php // echo $form->field($model, 'telefono') ?>

    <?php // echo $form->field($model, 'origen_financiamiento') ?>

    <?php // echo $form->field($model, 'id_localidad') ?>

    <?php // echo $form->field($model, 'estado') ?>

    <div class="form-group">
        <?= Html::submitButton('Search', ['class' => 'btn btn-primary']) ?>
        <?= Html::resetButton('Reset', ['class' => 'btn btn-default']) ?>        
    </div>

    <?php ActiveForm::end(); ?>

</div>
