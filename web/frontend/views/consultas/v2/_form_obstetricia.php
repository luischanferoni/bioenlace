<?php

use yii\helpers\Html;
use yii\widgets\ActiveForm;
use kartik\date\DatePicker;
use wbraganca\dynamicform\DynamicFormWidget;

use yii\helpers\ArrayHelper; 
use yii\helpers\Url;

/* @var $this yii\web\View */
/* @var $model common\models\Consulta */
/* @var $form yii\widgets\ActiveForm */
?>

<div class="form-card card mb-3 border border-2">
    <div class="card-header d-flex justify-content-between">
        <div class="header-title">
            <h4 class="card-title">Datos del Embarazo</h4>
        </div>
    </div>
    <div class="card-body">
        <?php $form = ActiveForm::begin(['id' => 'form-obstetricia', 'enableClientValidation' => false]); ?>

        <div class="row" style="padding: 1%">    
            <div class="col-3">    

                <?=
                $form->field($modeloEmbarazo, 'fum')
                        ->widget(DatePicker::className(), [
                            'name' => 'check_issue_date',
                            'value' => date('Y-M-d', strtotime('+2 days')),
                            'options' => ['placeholder' => '-Seleccione una fecha-'],
                            'pluginOptions' => [
                                'format' => 'yyyy-mm-dd',
                                'autoclose' => true,
                            ]
                ])->label('FUM');
                ?>

            </div>       
            <div class="col-3">    

                <?=
                $form->field($modeloEmbarazo, 'fpp')
                        ->widget(DatePicker::className(), [
                            'name' => 'check_issue_date',
                            'value' => date('Y-M-d', strtotime('+2 days')),
                            'options' => ['placeholder' => '-Seleccione una fecha-'],
                            'pluginOptions' => [
                                'format' => 'yyyy-mm-dd',
                                'autoclose' => true,
                            ]
                ])->label('FPP');
                ?>

            </div>       
            
            <div class="col-3">    

                <?=
                $form->field($modeloEmbarazo, 'fecha_diagnostico')
                        ->widget(DatePicker::className(), [
                            'name' => 'check_issue_date',
                            'value' => date('Y-M-d', strtotime('+2 days')),
                            'options' => ['placeholder' => '-Seleccione una fecha-'],
                            'pluginOptions' => [
                                'format' => 'yyyy-mm-dd',
                                'autoclose' => true,
                            ]
                ]);
                ?>

            </div>        
            <div class="col-3">    

                <?=
                $form->field($modeloEmbarazo, 'edad_gestacional')->textInput([
                    'type' => 'number'
            ]);
                ?>

            </div>        
        </div>

        <div class="row">
            <div class="col-xs-12">
                <?= Html::submitButton('Guardar', ['class' => 'btn btn-primary rounded-pill float-xl-end']) ?>
            </div>
        </div>
        <?php ActiveForm::end(); ?>
    </div>
</div>