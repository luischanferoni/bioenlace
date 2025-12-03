<?php

use yii\helpers\Html;
use yii\widgets\ActiveForm;

/* @var $this yii\web\View */
/* @var $model common\models\CovidFactoresRiesgo */
/* @var $form ActiveForm */
?>
<div class="covid_entrevista_telefonica-_factores_riesgo">

<div class="row">
        <div class="col-md-3 col-sm-12 col-xs-12"><?= $form->field($model, 'asma')->checkbox(); ?></div>
        <div class="col-md-3 col-sm-12 col-xs-12"><?= $form->field($model, 'diabetes')->checkbox(); ?></div>
        <div class="col-md-3 col-sm-12 col-xs-12"><?= $form->field($model, 'dialisis')->checkbox(); ?></div>
        <div class="col-md-3 col-sm-12 col-xs-12"><?= $form->field($model, 'embarazo_puerperio')->checkbox(); ?></div>
</div>
<div class="row">
        <div class="col-md-3 col-sm-12 col-xs-12"><?= $form->field($model, 'enfermedad_hepatica')->checkbox(); ?></div>
        <div class="col-md-3 col-sm-12 col-xs-12"><?= $form->field($model, 'enfermedad_neurologica')->checkbox(); ?></div>
        <div class="col-md-3 col-sm-12 col-xs-12"><?= $form->field($model, 'oncologico')->checkbox(); ?></div>
        <div class="col-md-3 col-sm-12 col-xs-12"><?= $form->field($model, 'enfermedad_renal')->checkbox(); ?></div>
</div>
<div class="row">
        <div class="col-md-3 col-sm-12 col-xs-12"><?= $form->field($model, 'epoc')->checkbox(); ?></div>
        <div class="col-md-3 col-sm-12 col-xs-12"><?= $form->field($model, 'fumador_exfumador')->checkbox(); ?></div>
        <div class="col-md-3 col-sm-12 col-xs-12"><?= $form->field($model, 'enfermedad_cardiovascular')->checkbox(); ?></div>
        <div class="col-md-3 col-sm-12 col-xs-12"><?= $form->field($model, 'inmunosuprimido')->checkbox(); ?></div>
</div>
<div class="row">
        <div class="col-md-3 col-sm-12 col-xs-12"><?= $form->field($model, 'obeso')->checkbox(); ?></div>
        <div class="col-md-3 col-sm-12 col-xs-12"><?= $form->field($model, 'neumonia_previa')->checkbox(); ?></div>
        <div class="col-md-3 col-sm-12 col-xs-12"><?= $form->field($model, 'tuberculosis')->checkbox(); ?></div>
        <div class="col-md-3 col-sm-12 col-xs-12"><?= $form->field($model, 'hta')->checkbox(); ?></div>
</div>
<div class="row">
        <div class="col-md-3 col-sm-12 col-xs-12"><?= $form->field($model, 'otro')->checkbox(); ?></div>
        <div class="col-md-4 col-sm-12 col-xs-12"><?= $form->field($model, 'otro_texto') ?></div>
        <div class="col-md-5 col-sm-12 col-xs-12"><?= $form->field($model, 'medicacion') ?></div>
    
</div>  
</div><!-- covid_entrevista_telefonica-_factores_riesgo -->
