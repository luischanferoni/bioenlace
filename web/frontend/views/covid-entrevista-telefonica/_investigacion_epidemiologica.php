<?php

use yii\helpers\Html;
use yii\widgets\ActiveForm;

/* @var $this yii\web\View */
/* @var $model common\models\CovidInvestigacionEpidemiologica */
/* @var $form ActiveForm */
?>
<div class="covid_entrevista_telefonica-_investigacion_epidemiologica">
    <div class="row">
        <div class="col-md-4 col-sm-12 col-xs-12"><?= $form->field($model, 'fecha_inicio_sintomas')->widget(\yii\jui\DatePicker::className(), [
    'options' => ['class' => 'form-control'],
]) ?></div>
         <div class="col-md-4 col-sm-12 col-xs-12"><?= $form->field($model, 'fecha_notificacion_positivo')->widget(\yii\jui\DatePicker::className(), [
    'options' => ['class' => 'form-control'],
]) ?></div>
         <div class="col-md-4 col-sm-12 col-xs-12"><?= $form->field($model, 'fecha_fin_aislamiento')->widget(\yii\jui\DatePicker::className(), [
    'options' => ['class' => 'form-control'],
]) ?></div>
    </div>
    <div class="row">
        <div class="col-md-3 col-sm-12 col-xs-12">
        <?= $form->field($model, 'internacion')->radioList(array(0 => 'No', 1 => 'Si' )); ?></div>
        <div class="col-md-3 col-sm-12 col-xs-12">
        <?= $form->field($model, 'requiere_oxigeno')->radioList(array(0 => 'No', 1 => 'Si' )); ?></div>
    
        <div class="col-md-3 col-sm-12 col-xs-12">
        <?= $form->field($model, 'respirador')->radioList(array(0 => 'No', 1 => 'Si' )); ?></div>
        <div class="col-md-3 col-sm-12 col-xs-12"><?= $form->field($model, 'internacion_uti')->radioList(array(0 => 'No', 1 => 'Si' )); ?></div>
    </div>
    <div class="row">
        <div class="col-md-6 col-sm-12 col-xs-12"><?= $form->field($model, 'sintomas')->radioList(array(0 => 'No', 1 => 'Si' )); ?></div>
    </div>
    <div class="row">
        <div class="col-md-3 col-sm-12 col-xs-12">
        <?= $form->field($model, 'fiebre')->checkbox(); ?></div>
        <div class="col-md-3 col-sm-12 col-xs-12">
        <?= $form->field($model, 'tos')->checkbox(); ?></div>
        <div class="col-md-3 col-sm-12 col-xs-12">
        <?= $form->field($model, 'diarrea_vomitos')->checkbox(); ?></div>
        <div class="col-md-3 col-sm-12 col-xs-12">
        <?= $form->field($model, 'anosmia_disgeusia')->checkbox(); ?></div>
    </div>
    <div class="row">
        <div class="col-md-3 col-sm-12 col-xs-12">
        <?= $form->field($model, 'dificultad_respiratoria')->checkbox(); ?></div>
        <div class="col-md-3 col-sm-12 col-xs-12">
        <?= $form->field($model, 'malestar_general')->checkbox(); ?></div>
        <div class="col-md-3 col-sm-12 col-xs-12">
        <?= $form->field($model, 'cefalea')->checkbox(); ?></div>
        <div class="col-md-3 col-sm-12 col-xs-12">
        <?= $form->field($model, 'rinitis_secrecion_nasal')->checkbox(); ?>
        </div>
</div>
        <?= $form->field($model, 'medicamentos')->radioList(array(0 => 'No', 1 => 'Si' )) ?>
         <div class="row">
                <div class="col-md-12 col-sm-12 col-xs-12"> <label>Indicado por:</label>
       </div></div>
        <div class="row">
                <div class="col-md-3 col-sm-12 col-xs-12">
        <?= $form->field($model, 'indicado_por_medico')->checkbox(); ?></div>
        <div class="col-md-3 col-sm-12 col-xs-12">
        <?= $form->field($model, 'indicado_equipo_seguimiento')->checkbox(); ?></div>
        <div class="col-md-3 col-sm-12 col-xs-12">
        <?= $form->field($model, 'indicado_familiar')->checkbox(); ?></div>
        <div class="col-md-3 col-sm-12 col-xs-12">
        <?= $form->field($model, 'indicado_automedicado')->checkbox(); ?></div>
        </div>
        <div class="row">
        <div class="col-md-3 col-sm-12 col-xs-12">
        <?= $form->field($model, 'paracetamol')->checkbox(); ?></div>
        <div class="col-md-3 col-sm-12 col-xs-12">
        <?= $form->field($model, 'azitromicina')->checkbox(); ?></div>
        <div class="col-md-3 col-sm-12 col-xs-12">
        <?= $form->field($model, 'corticoides')->checkbox(); ?></div>
        <div class="col-md-3 col-sm-12 col-xs-12">
        <?= $form->field($model, 'aspirina')->checkbox(); ?></div>
        </div>
        <div class="row">
        <div class="col-md-3 col-sm-12 col-xs-12">
        <?= $form->field($model, 'ivermectina')->checkbox(); ?></div>
        <div class="col-md-3 col-sm-12 col-xs-12">
        <?= $form->field($model, 'levofloxacina')->checkbox(); ?></div>
        <div class="col-md-3 col-sm-12 col-xs-12">
        <?= $form->field($model, 'amoxicilina_clavulanico')->checkbox(); ?></div>
        
    </div>
    <div class="row">
<div class="col-md-6 col-sm-12 col-xs-12">
            <?= $form->field($model, 'otro') ?></div>
</div>
</div><!-- covid_entrevista_telefonica-_investigacion_epidemiologica -->
