<?php

use yii\helpers\Html;
use yii\widgets\ActiveForm;

/* @var $this yii\web\View */
/* @var $model common\models\CovidEntrevistaTelefonica */
/* @var $form yii\widgets\ActiveForm */
?>

<div class="covid-entrevista-telefonica-form">

    <?php $form = ActiveForm::begin(); ?>
   
<?= $form->field($model, 'id_persona')->hiddenInput(['value'=> $model_persona->id_persona])->label(false) ?>

<div class="panel panel-default">
  <div class="panel-heading">
    <h3 class="panel-title">Información Personal</h3>
  </div>
  <div class="panel-body">
    <div class="row">
    <div class="col-md-4 col-sm-12 col-xs-12">
        <label>Apellido y Nombre:</label>&nbsp;<?= $model_persona->nombreCompleto  ?>
    </div>
    <div class="col-md-2 col-sm-12 col-xs-12">
        <label>Edad:</label>&nbsp;<?php echo ($model_persona->edad > 4)?$model_persona->edad:$model_persona->edadBebe; ?>
    </div>
    <div class="col-md-3 col-sm-12 col-xs-12">
        <label><?= $model_persona->tipoDocumento->nombre ?>:</label>&nbsp;<?= $model_persona->documento ?>
    </div>
    <div class="col-md-3 col-sm-12 col-xs-12">
        <label>Nro de contacto:</label>&nbsp;<?php echo $model_persona->telefonoContacto; ?>
    </div>
</div>
<div class="row">
    <div class="col-md-6 col-sm-12 col-xs-12">
        <label>Domicilio:</label>&nbsp;
    <?php if(is_object($model_persona->domicilioActivo)){ ?>
    <?= 'Calle:'. $model_persona->domicilioActivo->calle. ' Nro: '. $model_persona->domicilioActivo->numero. 'Mza: '.$model_persona->domicilioActivo->manzana. ' Lote: '. $model_persona->domicilioActivo->lote;?>
    <?php if(is_object($model_persona->domicilioActivo->modelBarrio)){?>
     <?= '  B°: '. $model_persona->domicilioActivo->modelBarrio->nombre; ?>
    <?php } } ?>
    </div>
    <div class="col-md-3 col-sm-12 col-xs-12">
    <?php if(is_object($model_persona->domicilioActivo)){ ?>
        <label>Localidad:</label>&nbsp;<?= $model_persona->domicilioActivo->idLocalidad->nombre; ?>
    <?php } ?>
    </div>
    
</div>
 <div class="row">
    <div class="col-md-4 col-sm-12 col-xs-12"><?= $form->field($model, 'resultado')->radioList([ 'positivo' => 'Positivo', 'negativo' => 'Negativo', ]) ?></div>
    <div class="col-md-4 col-sm-12 col-xs-12"><?= $form->field($model, 'telefono_contacto')->textInput(['maxlength' => true]) ?></div>
    <div class="col-md-4 col-sm-12 col-xs-12"><?= $form->field($model, 'ocupacion') ?></div>
 </div>
 <div class="row">
    <div class="col-md-4 col-sm-12 col-xs-12">
    <?= $form->field($model, 'vacunado')->radioList(array(0 => 'No', 1 => 'Si' )); ?>
    </div>
    <div class="col-md-4 col-sm-12 col-xs-12">
    <?= $form->field($model, 'fecha_primera_dosis')->widget(\yii\jui\DatePicker::className(), [
    'options' => ['class' => 'form-control'],
]) ?>
    </div>
    <div class="col-md-4 col-sm-12 col-xs-12">
    <?= $form->field($model, 'fecha_segunda_dosis')->widget(\yii\jui\DatePicker::className(), [
    'options' => ['class' => 'form-control'],
]) ?>
    </div>
</div>
<div class="row">
    <div class="col-md-4 col-sm-12 col-xs-12"><?= $form->field($model, 'convivientes')->radioList(array(1 => 'Si', 0 => 'No')); ?></div>
    <div class="col-md-8 col-sm-12 col-xs-12"><?= $form->field($model, 'convivientes_datos') ?></div>
 </div>
 </div>
 </div>

    <h2>Factores de Riesgo</h2>
    <?= $form->field($model, 'factores_riesgo')->radioList(array(0 => 'No', 1 => 'Si' )); ?>

    <?= $this->render('_factores_riesgo', 
                            [
                               'form' => $form,
                               'model' => $model_factores_riesgo
                            ]); 
                            ?>
    <h2>Investigaci&oacute;n Epidemiol&oacute;gica</h2>
    <?= $this->render('_investigacion_epidemiologica', 
                            [
                               'form' => $form,
                               'model' => $model_investigacion_epidemiologica
                            ]); 
                            ?>
    <h2>Entrevista Telef&oacute;nica</h2>
    <div class="row">
        <div class="col-md-4 col-sm-12 col-xs-12">
    <?= $form->field($model, 'continua_sintomas')->radioList(array(0 => 'No', 1 => 'Si' )); ?>
        </div>
    </div>
    <div class="row">
        <div class="col-md-4 col-sm-12 col-xs-12">
    <?= $form->field($model, 'falta_aire')->radioList(array(0 => 'No', 1 => 'Si' )); ?></div>
<div class="col-md-4 col-sm-12 col-xs-12">
    <?= $form->field($model, 'falta_aire_reposo')->radioList(array(0 => 'No', 1 => 'Si' )); ?></div>
<div class="col-md-4 col-sm-12 col-xs-12">
    <?= $form->field($model, 'falta_aire_caminar')->radioList(array(0 => 'No', 1 => 'Si' )); ?></div>
    </div>
    <div class="row">
<div class="col-md-4 col-sm-12 col-xs-12">
    <?= $form->field($model, 'dolor_pecho')->radioList(array(0 => 'No', 1 => 'Si' )); ?></div>
<div class="col-md-4 col-sm-12 col-xs-12">
    <?= $form->field($model, 'taquicardia_palpitaciones')->radioList(array(0 => 'No', 1 => 'Si' )); ?></div>
    </div>
    <div class="row">
<div class="col-md-4 col-sm-12 col-xs-12">
    <?= $form->field($model, 'perdida_memoria')->radioList(array(0 => 'No', 1 => 'Si' )); ?></div>
    </div>
    <div class="row">
        <div class="col-md-4 col-sm-12 col-xs-12">
    <?= $form->field($model, 'cefalea_dolor_cabeza')->radioList(array(0 => 'No', 1 => 'Si' )); ?></div>
    </div>

    <div class="row">
        <div class="col-md-4 col-sm-12 col-xs-12">
    <?= $form->field($model, 'falta_fuerza')->radioList(array(0 => 'No', 1 => 'Si' )); ?></div>
<div class="col-md-4 col-sm-12 col-xs-12">
    <?= $form->field($model, 'dolor_muscular')->radioList(array(0 => 'No', 1 => 'Si' )); ?></div>
    </div>

    <div class="row">
        <div class="col-md-4 col-sm-12 col-xs-12">
    <?= $form->field($model, 'secrecion_rinitis_constante')->radioList(array(0 => 'No', 1 => 'Si' )); ?></div>
    </div>
    <div class="row">
<div class="col-md-4 col-sm-12 col-xs-12">
    <?= $form->field($model, 'llanto_espontaneo')->radioList(array(0 => 'No', 1 => 'Si' )); ?></div>
<div class="col-md-4 col-sm-12 col-xs-12">
    <?= $form->field($model, 'cuesta_salir_casa')->radioList(array(0 => 'No', 1 => 'Si' )); ?></div>
    </div>
    <div class="row">
<div class="col-md-4 col-sm-12 col-xs-12">
    <?= $form->field($model, 'tristeza_angustia')->radioList(array(0 => 'No', 1 => 'Si' )); ?></div>
    </div>
    <div class="row">
<div class="col-md-4 col-sm-12 col-xs-12">
    <?= $form->field($model, 'dificultad_realizar_tareas')->radioList(array(0 => 'No', 1 => 'Si' )); ?></div>
    </div>

    
    <div class="form-group">
        <?= Html::submitButton('Guardar', ['class' => 'btn btn-success']) ?>
    </div>

    <?php ActiveForm::end(); ?>

</div>
