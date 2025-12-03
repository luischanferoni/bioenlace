<?php

use yii\helpers\Html;
use yii\widgets\ActiveForm;

/* @var $this yii\web\View */
/* @var $model common\models\busquedas\CovidEntrevistaTelefonicaBusqueda */
/* @var $form yii\widgets\ActiveForm */
?>

<div class="covid-entrevista-telefonica-search">

    <?php $form = ActiveForm::begin([
        'action' => ['index'],
        'method' => 'get',
    ]); ?>

    <?= $form->field($model, 'id') ?>

    <?= $form->field($model, 'id_persona') ?>

    <?= $form->field($model, 'convivientes') ?>

    <?= $form->field($model, 'convivientes_datos') ?>

    <?= $form->field($model, 'resultado') ?>

    <?php // echo $form->field($model, 'telefono_contacto') ?>

    <?php // echo $form->field($model, 'vacunado') ?>

    <?php // echo $form->field($model, 'fecha_primera_dosis') ?>

    <?php // echo $form->field($model, 'fecha_segunda_dosis') ?>

    <?php // echo $form->field($model, 'continua_sintomas') ?>

    <?php // echo $form->field($model, 'falta_aire') ?>

    <?php // echo $form->field($model, 'falta_aire_reposo') ?>

    <?php // echo $form->field($model, 'falta_aire_caminar') ?>

    <?php // echo $form->field($model, 'dolor_pecho') ?>

    <?php // echo $form->field($model, 'taquicardia_palpitaciones') ?>

    <?php // echo $form->field($model, 'perdida_memoria') ?>

    <?php // echo $form->field($model, 'cefalea_dolor_cabeza') ?>

    <?php // echo $form->field($model, 'falta_fuerza') ?>

    <?php // echo $form->field($model, 'dolor_muscular') ?>

    <?php // echo $form->field($model, 'secrecion_rinitis_constante') ?>

    <?php // echo $form->field($model, 'llanto_espontaneo') ?>

    <?php // echo $form->field($model, 'cuesta_salir_casa') ?>

    <?php // echo $form->field($model, 'tristeza_angustia') ?>

    <?php // echo $form->field($model, 'dificultad_realizar_tareas') ?>

    <div class="form-group">
        <?= Html::submitButton('Search', ['class' => 'btn btn-primary']) ?>
        <?= Html::resetButton('Reset', ['class' => 'btn btn-outline-secondary']) ?>
    </div>

    <?php ActiveForm::end(); ?>

</div>
