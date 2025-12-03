<?php

use yii\helpers\Html;
use yii\widgets\ActiveForm;
use kartik\date\DatePicker;
use kartik\time\TimePicker;

/* @var $this yii\web\View */
/* @var $model common\models\SegNivelInternacionAtencionesEnfermeria */
/* @var $form yii\widgets\ActiveForm */
?>

<div class="seg-nivel-internacion-atenciones-enfermeria-form">

        <?php $form = ActiveForm::begin(); ?>
    <?= $form->field($model, 'id_internacion')->hiddenInput(['value' => $id_internacion ])->label(false) ?>

    <div class="row">
    <div class="col-md-4 col-sm-12 col-xs-12">
            <div class="panel panel-default">
                <div class="panel-heading clearfix">
                    <i class="icon-calendar"></i>
                    <h3 class="panel-title">Control Tensión Arterial #1</h3>
                </div>
                <div class="panel-body">
                    <div class="row">
                        <div class="col-md-6 col-sm-12 col-xs-12">
                            <?php /* sistolica == 271649006 */?>
                            <?= Html::label('Sistólica', 'TensionArterial1[271649006]', ['class' => 'control-label']) ?>
                            <?= Html::input('text', 'TensionArterial1[271649006]', '', ['class' => 'form-control', 'placeHolder' => 'Ej. 110']) ?>
                            <!-- <div class="input-group"></div> -->
                        </div>                
                        <div class="col-md-6 col-sm-12 col-xs-12">
                            <?php /* diastolica == 271650006 */?>
                            <?= Html::label('Diastólica', 'TensionArterial1[271650006]', ['class' => 'control-label']) ?>
                            <?= Html::input('text', 'TensionArterial1[271650006]', '', ['class' => 'form-control', 'placeHolder' => 'Ej. 070']) ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4 col-sm-12 col-xs-12">
            <div class="panel panel-default">
                <div class="panel-heading clearfix">
                    <i class="icon-calendar"></i>
                    <h3 class="panel-title">Control Tensión Arterial #2</h3>
                </div>
                <div class="panel-body">
                    <div class="row">
                        <div class="col-md-6 col-sm-12 col-xs-12">
                            <?= Html::label('Sistólica', 'TensionArterial2[271649006]', ['class' => 'control-label']) ?>
                            <?= Html::input('text', 'TensionArterial2[271649006]', '', ['class' => 'form-control', 'placeHolder' => 'Ej. 110']) ?>
                        </div>                
                        <div class="col-md-6 col-sm-12 col-xs-12">
                            <?= Html::label('Diastólica', 'TensionArterial2[271650006]', ['class' => 'control-label']) ?>
                            <?= Html::input('text', 'TensionArterial2[271650006]', '', ['class' => 'form-control', 'placeHolder' => 'Ej. 070']) ?>
                        </div>
                    </div>
                </div>
            </div>            
        </div>
        <div class="col-md-3 col-sm-12 col-xs-12">
            <div class="panel panel-default">
                <div class="panel-heading clearfix">
                    <i class="icon-calendar"></i>
                    <h3 class="panel-title">Control Peso/Talla</h3>
                </div>
                <div class="panel-body">
                    <div class="row">
                        <div class="col-md-6 col-sm-12 col-xs-12">
                            <?php /* peso == 162879003 */?>
                            <?= Html::label('Peso', '162879003p', ['class' => 'control-label']) ?>
                            <?= Html::input('text', '162879003p', '', ['class' => 'form-control', 'placeHolder' => 'En kg.']) ?>   
                        </div>
                        <div class="col-md-6 col-sm-12 col-xs-12">
                            <?php /* talla == 162879003 */?>
                            <?= Html::label('Talla', '162879003t', ['class' => 'control-label']) ?>
                            <?= Html::input('text', '162879003t', '', ['class' => 'form-control', 'placeHolder' => 'En cm.']) ?>   
                        </div>                
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-12 col-xs-12">
            <div class="panel panel-default">
                <div class="panel-heading clearfix">
                    <i class="icon-calendar"></i>
                    <h3 class="panel-title">Temperatura</h3>
                </div>
                <div class="panel-body">
                    <div class="row">
                        <div class="col-md-4 col-sm-12 col-xs-12">
                            <?= Html::label('Axilar', '415882003', ['class' => 'control-label']) ?>
                            <?= Html::input('text', '415882003', '', ['class' => 'form-control', 'placeHolder' => '36.2']) ?>
                        </div>                
                        <div class="col-md-4 col-sm-12 col-xs-12">
                            <?= Html::label('Rectal', '307047009', ['class' => 'control-label']) ?>
                            <?= Html::input('text', '307047009', '', ['class' => 'form-control', 'placeHolder' => '36.5']) ?>
                        </div>
                        <div class="col-md-4 col-sm-12 col-xs-12">
                            <?= Html::label('Digital', '307047010', ['class' => 'control-label']) ?>
                            <?= Html::input('text', '307047010', '', ['class' => 'form-control', 'placeHolder' => '36.5']) ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-12 col-xs-12">
            <div class="panel panel-default">
                <div class="panel-heading clearfix">
                    <i class="icon-calendar"></i>
                    <h3 class="panel-title">Control de diuresis</h3>
                </div>
                <div class="panel-body">
                    <div class="row">
                        <div class="col-md-12 col-sm-12 col-xs-12">
                            <?= Html::label('Medición de la diuresis', '364200006', ['class' => 'control-label']) ?>
                            <?= Html::input('text', '364200006', '', ['class' => 'form-control', 'placeHolder' => 'En ml']) ?>
                        </div>                                        
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="row">
        <div class="col-md-3 col-sm-4 col-xs-4">
            <div class="panel panel-default">
                <div class="panel-heading clearfix">
                    <i class="icon-calendar"></i>
                    <h3 class="panel-title">Control Heces/Orina</h3>
                </div>
                <div class="panel-body">
                    <div class="row">
                        <div class="col-md-6 col-sm-12 col-xs-12">
                            <?= Html::label('Orina', 'orina', ['class' => 'control-label']) ?>
                            <?= Html::input('text', 'orina', '', ['class' => 'form-control']) ?>   
                        </div>
                        <div class="col-md-6 col-sm-12 col-xs-12">
                            <?= Html::label('Heces', 'heces', ['class' => 'control-label']) ?>
                            <?= Html::input('text', 'heces', '', ['class' => 'form-control']) ?>   
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-4 col-xs-4">
            <div class="panel panel-default">
                <div class="panel-heading clearfix">
                    <i class="icon-calendar"></i>
                    <h3 class="panel-title">Control Glucemia Capilar</h3>
                </div>
                <div class="panel-body">
                    <div class="row">
                        <div class="col-md-12 col-sm-12 col-xs-12">
                            <?= Html::label('Glucemia Capilar', '434912009', ['class' => 'control-label']) ?>
                            <?= Html::input('text', '434912009', '', ['class' => 'form-control']) ?>   
                        </div> 
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-4 col-xs-4">
            <div class="panel panel-default">
                <div class="panel-heading clearfix">
                    <i class="icon-calendar"></i>
                    <h3 class="panel-title">Control C. A.</h3>
                </div>
                <div class="panel-body">
                    <div class="row">
                        <div class="col-md-12 col-sm-12 col-xs-12">
                            <?= Html::label('Circunferencia Abdominal', '396552003', ['class' => 'control-label']) ?>
                            <?= Html::input('text', '396552003', '', ['class' => 'form-control', 'placeHolder' => 'En cm.']) ?>   
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-4 col-xs-4">
            <div class="panel panel-default">
                <div class="panel-heading clearfix">
                    <i class="icon-calendar"></i>
                    <h3 class="panel-title">Control P.C.</h3>
                </div>
                <div class="panel-body">
                    <div class="row">
                        <div class="col-md-12 col-sm-12 col-xs-12">
                            <?= Html::label('Perimetro Cefalico', '363812007', ['class' => 'control-label']) ?>
                            <?= Html::input('text', '363812007', '', ['class' => 'form-control', 'placeHolder' => 'En cm.']) ?>   
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
     <div class="row">
        <div class="col-md-3 col-sm-4 col-xs-4">
            <div class="panel panel-default">
                <div class="panel-heading clearfix">
                    <i class="icon-calendar"></i>
                    <h3 class="panel-title">Frecuencia Cardíaca</h3>
                </div>
                <div class="panel-body">
                    <div class="row">
                        <div class="col-md-12 col-sm-12 col-xs-12">
                            <?php /* temperatura == 703421000 */?>
                            <?= Html::label('Frecuencia Cardíaca', '364075005', ['class' => 'control-label']) ?>
                            <?= Html::input('text', '364075005', '', ['class' => 'form-control']) ?>   
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-4 col-xs-4">
            <div class="panel panel-default">
                <div class="panel-heading clearfix">
                    <i class="icon-calendar"></i>
                    <h3 class="panel-title">Frecuencia Respiratoria</h3>
                </div>
                <div class="panel-body">
                    <div class="row">
                        <div class="col-md-12 col-sm-12 col-xs-12">
                            <?= Html::label('Frecuencia Respiratoria', '86290005', ['class' => 'control-label']) ?>
                            <?= Html::input('text', '86290005', '', ['class' => 'form-control']) ?>   
                        </div> 
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-4 col-xs-4">
            <div class="panel panel-default">
                <div class="panel-heading clearfix">
                    <i class="icon-calendar"></i>
                    <h3 class="panel-title">Saturación de Oxígeno</h3>
                </div>
                <div class="panel-body">
                    <div class="row">
                        <div class="col-md-12 col-sm-12 col-xs-12">
                            <?= Html::label('Saturación de Oxígeno', '103228002', ['class' => 'control-label']) ?>
                            <?= Html::input('text', '103228002', '', ['class' => 'form-control', 'placeHolder' => '']) ?>   
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-4 col-xs-4">
            <div class="panel panel-default">
                <div class="panel-heading clearfix">
                    <i class="icon-calendar"></i>
                    <h3 class="panel-title">Pulso</h3>
                </div>
                <div class="panel-body">
                    <div class="row">
                        <div class="col-md-12 col-sm-12 col-xs-12">
                            <?= Html::label('Pulso', '8499008', ['class' => 'control-label']) ?>
                            <?= Html::input('text', '8499008', '', ['class' => 'form-control', 'placeHolder' => '']) ?>   
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="row">        
        <div class="col-md-12 col-sm-12 col-xs-12">
            <div class="panel panel-default">
                <div class="panel-heading clearfix">
                    <i class="icon-calendar"></i>
                    <h3 class="panel-title">Otras Atenciones/Controles</h3>
                </div>
                <div class="panel-body">
                    <div class="row">
                        <div class="col-md-4 col-sm-12 col-xs-12">
                            <div class="form-check">
                                <label class="form-check-label">
                                    <input type="checkbox" name="nebulizacion" value="nebulizacion"> Nebulización
                                </label><br>
                                <label class="form-check-label">
                                    <input type="checkbox" name="rescate_sbo" value="rescate_sbo"> Rescate y SBO
                                </label><br>
                                <label class="form-check-label">
                                    <input type="checkbox" name="inyectable" value="inyectable"> Inyectable
                                </label>

                            </div>
                        </div>
                        <div class="col-md-4 col-sm-12 col-xs-12">
                            <div class="form-check">
                                <label class="form-check-label">
                                    <input type="checkbox" name="inmunizacion" value="inmunizacion"> Inmunización
                                </label><br>
                                <label class="form-check-label">
                                    <input type="checkbox" name="extraccion_puntos" value="extraccion_puntos"> Extracción de puntos
                                </label><br>
                                <label class="form-check-label">
                                    <input type="checkbox" name="curacion" value="curacion"> Curación
                                </label>                            
                            </div>
                        </div>
                        <div class="col-md-4 col-sm-12 col-xs-12">
                            <div class="form-check">                        
                                <label class="form-check-label">
                                    <input type="checkbox" name="internacion_abreviada" value="internacion_abreviada"> Internación abreviada
                                </label><br>
                                <label class="form-check-label">
                                    <input type="checkbox" name="visita_domiciliaria" value="visita_domiciliaria"> Apetito
                                </label> <br>         
                                <label class="form-check-label">
                                    <input type="checkbox" name="electrocardiograma" value="electrocardiograma"> Electrocardiograma
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>     
    <div class="row">        
        <div class="col-md-8 col-sm-12 col-xs-12">
            <div class="panel panel-default">
                <div class="panel-heading clearfix">
                    <i class="icon-calendar"></i>
                    <h3 class="panel-title">Observaciones</h3>
                </div>
                <div class="panel-body">
                    <div class="row">
                        <div class="col-md-12 col-sm-12 col-xs-12">
                            <?= Html::input('text', 'observaciones', '', ['class' => 'form-control', 'placeHolder' => 'Aqui puede agregar detalles sobre la atención realizada.']) ?>                               
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4 col-sm-12 col-xs-12">
            <div class="panel panel-default">
                <div class="panel-heading clearfix">
                    <i class="icon-calendar"></i>
                    <h3 class="panel-title">Fecha</h3>
                </div>
                <div class="panel-body">
                    <div class="row">
                        <div class="col-md-6 col-sm-6 col-xs-6">
                             
                            <?=
                                $form->field($model, 'fecha')->
                                widget(DatePicker::className(), [
                                    'name' => 'check_issue_date',
                                    'value' => date('Y-M-d'),
                                    'options' => ['placeholder' => 'Seleccione Fecha'],
                                    'pluginOptions' => [
                                        'format' => 'dd/MM/yyyy',
                                        'autoclose' => true,
                                    ]
                                ]);
                            ?>               
                        </div>
                        <div class="col-md-6 col-sm-6 col-xs-6">
                            <?=
                                $form->field($model, 'hora')->widget(TimePicker::className(), [
                                    'pluginOptions' => [
                                        'defaultTime' => '08:00',
                                        'showSeconds' => false,
                                        'showMeridian' => false,
                                        'minuteStep' => 15
                                    ],
                                ]);
                            ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div> 
    <div class="form-group text-right">
        
        <?= Html::a('Cancelar', ['internacion/view',  'id' => $id_internacion], ['class' => 'btn btn-danger']) ?>
        <?= Html::submitButton($model->isNewRecord ? 'Guardar' : 'Modificar', ['class' => $model->isNewRecord ? 'btn btn-success' : 'btn btn-primary']) ?>
    </div>    
    <?php ActiveForm::end(); ?> 


</div>
