<?php

use yii\helpers\Html;
use yii\widgets\ActiveForm;

/* @var $this yii\web\View */
/* @var $model common\models\RrhhBusqueda */
/* @var $form yii\widgets\ActiveForm */
?>

<div class="rrhh-search">

    <?php $form = ActiveForm::begin([
        'action' => ['index'],
        'method' => 'get',
    ]); ?>

    <?= $form->field($model, 'id_rr_hh') ?>

    <?= $form->field($model, 'id_persona') ?>

    <?= $form->field($model, 'id_profesion') ?>

    <?= $form->field($model, 'id_especialidad') ?>

    <div class="form-group">
        <?= Html::submitButton('Search', ['class' => 'btn btn-primary']) ?>
        <?= Html::resetButton('Reset', ['class' => 'btn btn-default']) ?>
    </div>

    <?php ActiveForm::end(); ?>

</div>
