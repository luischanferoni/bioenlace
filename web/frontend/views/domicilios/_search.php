<?php

use yii\helpers\Html;
use yii\widgets\ActiveForm;

/* @var $this yii\web\View */
/* @var $model common\models\busquedas\DomicilioBusqueda */
/* @var $form yii\widgets\ActiveForm */
?>

<div class="domicilio-search">

    <?php $form = ActiveForm::begin([
        'action' => ['index'],
        'method' => 'get',
    ]); ?>

    <?= $form->field($model, 'id_domicilio') ?>

    <?= $form->field($model, 'calle') ?>

    <?= $form->field($model, 'numero') ?>

    <?= $form->field($model, 'manzana') ?>

    <?= $form->field($model, 'lote') ?>

    <?php // echo $form->field($model, 'sector') ?>

    <?php // echo $form->field($model, 'grupo') ?>

    <?php // echo $form->field($model, 'torre') ?>

    <?php // echo $form->field($model, 'depto') ?>

    <?php // echo $form->field($model, 'barrio') ?>

    <?php // echo $form->field($model, 'id_localidad') ?>

    <?php // echo $form->field($model, 'latitud') ?>

    <?php // echo $form->field($model, 'longitud') ?>

    <?php // echo $form->field($model, 'urbano_rural') ?>

    <?php // echo $form->field($model, 'usuario_alta') ?>

    <?php // echo $form->field($model, 'fecha_alta') ?>

    <div class="form-group">
        <?= Html::submitButton('Search', ['class' => 'btn btn-primary']) ?>
        <?= Html::resetButton('Reset', ['class' => 'btn btn-default']) ?>
    </div>

    <?php ActiveForm::end(); ?>

</div>
