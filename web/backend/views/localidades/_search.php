<?php
/*
 * Autor: Guillermo Ponce
 * Creado: 16/10/2015
 * Modificado: 
*/

use yii\helpers\Html;
use yii\widgets\ActiveForm;

/* @var $this yii\web\View */
/* @var $model common\models\busquedas\LocalidadBusqueda */
/* @var $form yii\widgets\ActiveForm */
?>

<div class="localidad-search">

    <?php $form = ActiveForm::begin([
        'action' => ['index'],
        'method' => 'get',
    ]); ?>

    <!--<?= $form->field($model, 'id_localidad') ?>-->

    <!--<?= $form->field($model, 'cod_sisa') ?>-->

    <!--<?= $form->field($model, 'cod_bahra') ?>-->

    <?= $form->field($model, 'nombre') ?>

    <?= $form->field($model, 'cod_postal') ?>
    
    <?= $form->field($model, 'id_departamento') //esta linea se agrego?>

    <!--<?php //echo $form->field($model, 'id_departamento') ?>-->

    <div class="form-group">
        <?= Html::submitButton('Search', ['class' => 'btn btn-primary']) ?>
        <?= Html::resetButton('Reset', ['class' => 'btn btn-default']) ?>
    </div>

    <?php ActiveForm::end(); ?>

</div>
