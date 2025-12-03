<?php

use yii\helpers\Html;
use yii\bootstrap5\ActiveForm;
use yii\helpers\Url;
use kartik\select2\Select2;
use yii\helpers\ArrayHelper;

use common\models\AtencionesEnfermeria;
use common\models\Rrhh_efector;
use yii\web\JsExpression;

/* @var $this yii\web\View */
/* @var $model common\models\EncuestaParchesMamarios */
/* @var $form yii\widgets\ActiveForm */
?>

<style>
    div[role=radiogroup] label {
        margin-left: 25px;
    }

    .resultado_diferencia {
        font-size: 17px;
        font-weight: bold;
        padding: 5px;
    }
</style>

<div class="encuesta-parches-mamarios-form">

    <?php $form = ActiveForm::begin(['enableClientValidation'=> false]); ?>

    <?= $form->errorSummary($model); ?>

    <?php if(isset($modelAtencionEnfermeria)){
        
                $datos = json_decode($modelAtencionEnfermeria->datos, true);
    ?>

    <?= $form->errorSummary($modelAtencionEnfermeria); ?>
    
    <?php } ?>


    <div class="card">
        <div class="card-body">
            <div class="row">
                <div class="col-md-4 col-sm-12 col-xs-12">
                    <?php
                    $data = [];
                    if (!is_null($model->id_operador) && $model->id_operador != "") {
                        $data = [$model->id_operador => $model->operador->persona->apellido . ', ' . $model->operador->persona->nombre];
                    }

                    echo $form->field($model, 'id_operador')->widget(Select2::classname(), [
                        'data' => $data,
                        'theme' => 'default',
                        'language' => 'es',
                        'options' => ['placeholder' => 'Seleccione el Profesional que realiza la práctica'],
                        'pluginOptions' => [
                            'allowClear' => true,
                            'minimumInputLength' => 3,
                            'ajax' => [
                                'url' => Url::to(['rrhh-efector/rrhh-autocomplete']),
                                'dataType' => 'json',
                                'data' => new JsExpression('function(params) { return {q:params.term}; }'),
                            ],
                        ],
                    ]);
                    ?>
                </div>
                <div class="col-md-4 col-sm-12 col-xs-12">
                    <?= $form->field($model, 'fecha_prueba')->widget(\yii\jui\DatePicker::className(), [
                        'options' => ['class' => 'form-control'],
                    ]) ?>
                </div>

                <div class="col-md-4 col-sm-12 col-xs-12">
                    <?= $form->field($model, 'numero_serie')->textInput(['maxlength' => true]) ?>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header bg-soft-info">
            <h4>Precauciones de la prueba</h4>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-3 col-sm-12 col-xs-12">
                    <?= $form->field($model, 'antecedente_cancer_mama')->inline(true)->radioList(array('NO' => 'NO', 'SI' => 'SI')); ?>
                </div>

                <div class="col-md-3 col-sm-12 col-xs-12">
                    <?= $form->field($model, 'antecedente_cirugia_mamaria')->inline(true)->radioList(array('NO' => 'NO', 'SI' => 'SI')); ?>
                </div>

                <div class="col-md-3 col-sm-12 col-xs-12">
                    <?= $form->field($model, 'actualmente_amamantando')->inline(true)->radioList(array('NO' => 'NO', 'SI' => 'SI')); ?>
                </div>

                <div class="col-md-3 col-sm-12 col-xs-12">
                    <?= $form->field($model, 'sintomas_enfermedad_mamaria')->inline(true)->radioList(array('NO' => 'NO', 'SI' => 'SI')); ?>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header bg-soft-info">
            <h4>Cuestionario del paciente</h4>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-2 col-sm-12 col-xs-12">
                    <div class="form-group field-162879003p">
                        <?php /* peso == 162879003 */ ?>
                        <?= Html::label('Peso', '162879003p', ['class' => 'control-label']) ?>
                        <?= Html::input('text', '162879003p', isset($datos['162879003p']) ? $datos['162879003p'] : '', ['class' => 'form-control', 'placeHolder' => 'En kg.']) ?>
                    </div>
                </div>
                <div class="col-md-2 col-sm-12 col-xs-12">
                    <div class="form-group field-162879003t">
                        <?php /* talla == 162879003 */ ?>
                        <?= Html::label('Talla', '162879003t', ['class' => 'control-label']) ?>
                        <?= Html::input('text', '162879003t', isset($datos['162879003t']) ? $datos['162879003t'] : '', ['class' => 'form-control', 'placeHolder' => 'En cm.']) ?>
                    </div>
                </div>
                <div class="col-md-4 col-sm-12 col-xs-12">
                    <?= $form->field($model, 'consume_alcohol')->inline(true)->radioList(array('NO' => 'NO', 'SI' => 'SI')); ?>
                </div>
                <div class="col-md-4 col-sm-12 col-xs-12">
                    <?= $form->field($model, 'consume_tabaco')->inline(true)->radioList(array('NO' => 'NO', 'SI' => 'SI')); ?>
                </div>
            </div>
            <br>

            <div class="row">
                <div class="col-md-2 col-sm-12 col-xs-12">
                    <?= $form->field($model, 'edad_primer_periodo', ['labelOptions' =>  ['class' => 'control-label']])->textInput(); ?>
                </div>
                <div class="col-md-2 col-sm-12 col-xs-12">
                    <?= $form->field($model, 'tiene_hijos')->inline(true)->radioList(array('NO' => 'NO', 'SI' => 'SI')); ?>
                </div>
                <div class="col-md-2 col-sm-12 col-xs-12">
                    <?= $form->field($model, 'edad_primer_parto', ['labelOptions' =>  ['class' => 'control-label']])->textInput() ?>
                </div>
                <div class="col-md-2 col-sm-12 col-xs-12">
                    <?= $form->field($model, 'paso_menospausia')->inline(true)->radioList(array('NO' => 'NO', 'SI' => 'SI')); ?>
                </div>
                <div class="col-md-2 col-sm-12 col-xs-12">
                    <?= $form->field($model, 'edad_menospausia', ['labelOptions' =>  ['class' => 'control-label']])->textInput([
                        'disabled' => ($model->paso_menospausia == 'NO' || is_null($model->paso_menospausia) || empty($model->paso_menospausia)) ? 'disabled' : false,
                    ]) ?>
                </div>
            </div>
            <br>
            <div class="row">
                <div class="col-md-3 col-sm-12 col-xs-12">
                    <?= $form->field($model, 'terapia_remplazo_hormonal')->inline(true)->radioList(['NO' => 'NO', 'SI' => 'SI']); ?>
                </div>
                <div class="col-md-2 col-sm-12 col-xs-12">
                    <?= $form->field($model, 'senos_densos', ['labelOptions' =>  ['class' => 'control-label']])->dropDownList(['NO' => 'NO', 'SI' => 'SI', 'NO SE' => 'NO SE'], ['prompt' => '']); ?>
                </div>
                <div class="col-md-3 col-sm-12 col-xs-12">
                    <?= $form->field($model, 'biopsia_mamaria')->inline(true)->radioList(array('NO' => 'NO', 'SI' => 'SI')); ?>
                </div>
                <div class="col-md-2 col-sm-12 col-xs-12">
                    <?= $form->field($model, 'fecha_biopsia', ['labelOptions' =>  ['class' => 'control-label']])->widget(\yii\jui\DatePicker::className(), [
                        'options' => [
                            'class' => 'form-control',
                            'disabled' => ($model->biopsia_mamaria == 'NO' || is_null($model->biopsia_mamaria) || empty($model->biopsia_mamaria)) ? 'disabled' : false
                        ],
                    ]) ?>
                </div>
                <div class="col-md-2 col-sm-12 col-xs-12">
                    <?= $form->field($model, 'resultado_biopsia', ['labelOptions' =>  ['class' => 'control-label']])->dropDownList(
                        ['Desconocido' => 'Desconocido', 'Hiperpasia' => 'Hiperpasia', 'Atipia' => 'Atipia',],
                        [
                            'prompt' => '',
                            'disabled' => ($model->paso_menospausia == 'NO' || is_null($model->paso_menospausia) || empty($model->paso_menospausia)) ? 'disabled' : false,
                        ]
                    ) ?>
                </div>
            </div>
            <br>
            <div class="row">
                <div class="col-md-3 col-sm-12 col-xs-12">
                    <?= $form->field($model, 'antecedente_familiar_cancer_mamario_ovarico', ['labelOptions' =>  ['class' => 'control-label']])->dropDownList(['NO' => 'NO', 'SI' => 'SI', 'NO SE' => 'NO SE',], ['prompt' => '']) ?>
                </div>
                <div class="col-md-2 col-sm-12 col-xs-12">
                    <?= $form->field($model, 'mamografia')->inline(true)->radioList(array('NO' => 'NO', 'SI' => 'SI')); ?>
                </div>
                <div class="col-md-3 col-sm-12 col-xs-12">
                    <?= $form->field($model, 'fecha_ultima_mamografia', ['labelOptions' =>  ['class' => 'control-label']])->widget(\yii\jui\DatePicker::className(), [
                        'options' => [
                            'class' => 'form-control',
                            'disabled' => ($model->mamografia == 'NO' || is_null($model->mamografia) || empty($model->mamografia)) ? 'disabled' : false
                        ],
                    ]) ?>
                </div>
                <div class="col-md-2 col-sm-12 col-xs-12">
                    <?= $form->field($model, 'prueba_adicional')->inline(true)->radioList(array('NO' => 'NO', 'SI' => 'SI')); ?>
                </div>
                <div class="col-md-2 col-sm-12 col-xs-12">
                    <?= $form->field($model, 'prueba_adicional_tipo', ['labelOptions' =>  ['class' => 'control-label']])->dropDownList(['Ultrasonido' => 'Ultrasonido', 'RMI' => 'RMI', 'Biopsia' => 'Biopsia',], ['prompt' => '']) ?>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header bg-soft-info">
            <h4>Parches</h4>
        </div>
        <div class="card-body">
            <h6>Sección A</h6>
            <div class="row">
                <div class="col-md-4 col-sm-12 col-xs-12">
                    <?= $form->field($model, 'a_izquierdo')->dropDownList(range(0, 18)) ?>
                </div>

                <div class="col-md-4 col-sm-12 col-xs-12">
                    <?= $form->field($model, 'a_derecho')->dropDownList(range(0, 18)) ?>
                </div>

                <div class="col-md-4 col-sm-12 col-xs-12">
                    <label>A Diferencia</label>
                    <?= Html::hiddenInput('EncuestaParchesMamarios[a_diferencia]', $model->a_diferencia, ['id' => 'encuestaparchesmamarios-a_diferencia']) ?>
                    <div class="resultado_diferencia" id="a_diferencia"><?= $model->a_diferencia ?></div>
                </div>
            </div>

            <h6>Sección B</h6>
            <div class="row">
                <div class="col-md-4 col-sm-12 col-xs-12">
                    <?= $form->field($model, 'b_izquierdo')->dropDownList(range(0, 18)) ?>
                </div>

                <div class="col-md-4 col-sm-12 col-xs-12">
                    <?= $form->field($model, 'b_derecho')->dropDownList(range(0, 18)) ?>
                </div>

                <div class="col-md-4 col-sm-12 col-xs-12">
                    <label>B Diferencia</label>
                    <?= Html::hiddenInput('EncuestaParchesMamarios[b_diferencia]', $model->b_diferencia, ['id' => 'encuestaparchesmamarios-b_diferencia']) ?>
                    <div class="resultado_diferencia" id="b_diferencia"><?= $model->b_diferencia ?></div>
                </div>
            </div>

            <h6>Sección C</h6>
            <div class="row">
                <div class="col-md-4 col-sm-12 col-xs-12">
                    <?= $form->field($model, 'c_izquierdo')->dropDownList(range(0, 18)) ?>
                </div>

                <div class="col-md-4 col-sm-12 col-xs-12">
                    <?= $form->field($model, 'c_derecho')->dropDownList(range(0, 18)) ?>
                </div>

                <div class="col-md-4 col-sm-12 col-xs-12">
                    <label>C Diferencia</label>
                    <?= Html::hiddenInput('EncuestaParchesMamarios[c_diferencia]', $model->c_diferencia, ['id' => 'encuestaparchesmamarios-c_diferencia']) ?>
                    <div class="resultado_diferencia" id="c_diferencia"><?= $model->c_diferencia ?></div>
                </div>
            </div>

            <h6>Resultado</h6>
            <div class="row">
                <div class="col-md-6 col-sm-12 col-xs-12">
                    <div class="resultado_diferencia" id="resultado"><?= $model->resultado ?></div>
                    <?= Html::hiddenInput('EncuestaParchesMamarios[resultado]', $model->resultado, ['id' => 'encuestaparchesmamarios-resultado']) ?>
                </div>
                <div class="col-md-6 col-sm-12 col-xs-12">
                    <?= $form->field($model, 'resultado_indicado')->dropDownList(['No Significativa' => 'No Significativa', 'Significativa' => 'Significativa', 'No concluyente' => 'No concluyente',], ['prompt' => '']) ?>
                </div>
            </div>

            <?= $form->field($model, 'observaciones')->textarea(['rows' => 6]) ?>
        
        </div>
    </div>

    <div class="form-group">
        <?= Html::submitButton('Guardar', ['class' => 'btn btn-success']) ?>
    </div>

    <?php ActiveForm::end(); ?>


</div>

<?php
$this->registerJs("
    
    $(document).on('change', '#encuestaparchesmamarios-tiene_hijos', function(e) {
        if (e.target.value == \"SI\") {
            $('#encuestaparchesmamarios-edad_primer_parto').attr('disabled', false);
        } else {
            $('#encuestaparchesmamarios-edad_primer_parto').attr('disabled', true);
        }
    });

    $(document).on('change', '#encuestaparchesmamarios-paso_menospausia', function(e) {
        if (e.target.value == \"SI\") {
            $('#encuestaparchesmamarios-edad_menospausia').attr('disabled', false);
        } else {
            $('#encuestaparchesmamarios-edad_menospausia').attr('disabled', true);
        }
    });

    $(document).on('change', '#encuestaparchesmamarios-biopsia_mamaria', function(e) {
        if (e.target.value == \"SI\") {
            $('#encuestaparchesmamarios-fecha_biopsia').attr('disabled', false);
            $('#encuestaparchesmamarios-resultado_biopsia').attr('disabled', false);
        } else {
            $('#encuestaparchesmamarios-fecha_biopsia').attr('disabled', true);
            $('#encuestaparchesmamarios-resultado_biopsia').attr('disabled', true);
        }
    });

    $(document).on('change', '#encuestaparchesmamarios-mamografia', function(e) {
        if (e.target.value == \"SI\") {
            $('#encuestaparchesmamarios-fecha_ultima_mamografia').attr('disabled', false);
        } else {
            $('#encuestaparchesmamarios-fecha_ultima_mamografia').attr('disabled', true);
        }
    });

    $('#encuestaparchesmamarios-prueba_adicional_tipo').attr('disabled', true);
    $(document).on('change', '#encuestaparchesmamarios-prueba_adicional', function(e) {
        if (e.target.value == \"SI\") {
            $('#encuestaparchesmamarios-prueba_adicional_tipo').attr('disabled', false);
        } else {
            $('#encuestaparchesmamarios-prueba_adicional_tipo').attr('disabled', true);
        }
    });

    $(document).on('change', '#encuestaparchesmamarios-a_izquierdo, #encuestaparchesmamarios-a_derecho', function() {
        var a = $('#encuestaparchesmamarios-a_izquierdo').val();
        var b = $('#encuestaparchesmamarios-a_derecho').val();
        var a_diferencia = Math.abs(a - b);
        $('#encuestaparchesmamarios-a_diferencia').val(a_diferencia);
        $('#a_diferencia').html(a_diferencia);
        if (a_diferencia > 3 || $('#encuestaparchesmamarios-b_diferencia').val() > 3 || $('#encuestaparchesmamarios-c_diferencia').val() > 3) {
            $('#encuestaparchesmamarios-resultado').val('Significativa');
            $('#encuestaparchesmamarios-resultado_indicado').val('Significativa');
            $('#resultado').html('Significativa').css({'color':'#a94442'});
        } else {
            $('#encuestaparchesmamarios-resultado').val('No Significativa');
            $('#encuestaparchesmamarios-resultado_indicado').val('No Significativa');
            $('#resultado').html('No Significativa').css({'color':'black'});
        }
    });

    $(document).on('change', '#encuestaparchesmamarios-b_izquierdo, #encuestaparchesmamarios-b_derecho', function() {
        var c = $('#encuestaparchesmamarios-b_izquierdo').val();
        var d = $('#encuestaparchesmamarios-b_derecho').val();
        var b_diferencia = Math.abs(c - d);
        $('#encuestaparchesmamarios-b_diferencia').val(b_diferencia);
        $('#b_diferencia').html(b_diferencia);
        if (b_diferencia > 3 || $('#encuestaparchesmamarios-a_diferencia').val() > 3 || $('#encuestaparchesmamarios-c_diferencia').val() > 3) {
            $('#encuestaparchesmamarios-resultado').val('Significativa');
            $('#encuestaparchesmamarios-resultado_indicado').val('Significativa');
            $('#resultado').html('Significativa').css({'color':'#a94442'});
        } else {
            $('#encuestaparchesmamarios-resultado').val('No Significativa');
            $('#encuestaparchesmamarios-resultado_indicado').val('No Significativa');
            $('#resultado').html('No Significativa').css({'color':'black'});
        }
    });

    $(document).on('change', '#encuestaparchesmamarios-c_izquierdo, #encuestaparchesmamarios-c_derecho', function() {
        var e = $('#encuestaparchesmamarios-c_izquierdo').val();
        var f = $('#encuestaparchesmamarios-c_derecho').val();
        var c_diferencia = Math.abs(e - f);
        $('#encuestaparchesmamarios-c_diferencia').val(c_diferencia);
        $('#c_diferencia').html(c_diferencia);
        if (c_diferencia > 3 || $('#encuestaparchesmamarios-a_diferencia').val() > 3 || $('#encuestaparchesmamarios-b_diferencia').val() > 3) {
            $('#encuestaparchesmamarios-resultado').val('Significativa');
            $('#encuestaparchesmamarios-resultado_indicado').val('Significativa');
            $('#resultado').html('Significativa').css({'color':'#a94442'});
        } else {
            $('#encuestaparchesmamarios-resultado').val('No Significativa');
            $('#encuestaparchesmamarios-resultado_indicado').val('No Significativa');
            $('#resultado').html('No Significativa').css({'color':'black'});
        }
    });

");
?>