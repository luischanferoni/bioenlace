<?php

use yii\helpers\Html;
use yii\widgets\ActiveForm;

/* @var $this yii\web\View */
/* @var $model common\models\busquedas\ProfesionalEfectorServicioBusqueda */
/* @var $form yii\widgets\ActiveForm */
?>

<div class="asignacion-pes-search">

    <?php $form = ActiveForm::begin([
        'action' => ['index'],
        'method' => 'get',
    ]); ?>

    <?= $form->field($model, 'id_profesional_efector_servicio')->label('ID PES') ?>
    <?= $form->field($model, 'id_persona') ?>
    <?= $form->field($model, 'nombrePersona') ?>

    <div class="form-group">
        <?= Html::submitButton('Buscar', ['class' => 'btn btn-primary']) ?>
        <?= Html::resetButton('Limpiar', ['class' => 'btn btn-default']) ?>
    </div>

    <?php ActiveForm::end(); ?>

</div>
