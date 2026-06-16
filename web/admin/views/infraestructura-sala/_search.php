<?php

use yii\helpers\Html;
use yii\widgets\ActiveForm;

/* @var $this yii\web\View */
/* @var $model common\models\busquedas\InfraestructuraSalaBusqueda */
/* @var $form yii\widgets\ActiveForm */
?>

<div class="infraestructura-sala-search">

    <?php $form = ActiveForm::begin([
        'action' => ['index'],
        'method' => 'get',
    ]); ?>

    <?= $form->field($model, 'id') ?>

    <?= $form->field($model, 'nro_sala') ?>

    <?= $form->field($model, 'descripcion') ?>

    <?= $form->field($model, 'covid') ?>

    <?= $form->field($model, 'id_responsable') ?>

    <?php // echo $form->field($model, 'id_piso') ?>

    <?php // echo $form->field($model, 'id_servicio') ?>

    <?php // echo $form->field($model, 'tipo_sala') ?>

    <div class="form-group">
        <?= Html::submitButton('Search', ['class' => 'btn btn-primary']) ?>
        <?= Html::resetButton('Reset', ['class' => 'btn btn-outline-secondary']) ?>
    </div>

    <?php ActiveForm::end(); ?>

</div>
