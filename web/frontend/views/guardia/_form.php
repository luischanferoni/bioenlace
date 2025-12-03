<?php

use yii\helpers\Html;
use yii\bootstrap5\ActiveForm;
use common\models\RrhhEfector;

use kartik\select2\Select2;
use kartik\date\DatePicker;
use kartik\time\TimePicker;
use yii\helpers\ArrayHelper;
use common\models\Guardia;
use common\models\Telefono;

/* @var $this yii\web\View */
/* @var $model common\models\Guardia */
/* @var $form yii\widgets\ActiveForm */
?>

<div class="guardia-form">
    
<?php $form = ActiveForm::begin(['enableClientValidation' => false]); ?>
    <?= $form->errorSummary($model); ?>
    <div class="col-sm-12 mb-5">
        <div class="card-group">
            <div class="card">
                <div class="row no-gutters">
                    <div class="col-md-4">
                        <div class="card-header bg-soft-primary d-flex justify-content-center align-items-center ">
                            <svg xmlns="http://www.w3.org/2000/svg" width="100" height="100" fill="currentColor" class="bi bi-person-lines-fill" viewBox="0 0 16 16">
                                <path d="M6 8a3 3 0 1 0 0-6 3 3 0 0 0 0 6zm-5 6s-1 0-1-1 1-4 6-4 6 3 6 4-1 1-1 1H1zM11 3.5a.5.5 0 0 1 .5-.5h4a.5.5 0 0 1 0 1h-4a.5.5 0 0 1-.5-.5zm.5 2.5a.5.5 0 0 0 0 1h4a.5.5 0 0 0 0-1h-4zm2 3a.5.5 0 0 0 0 1h2a.5.5 0 0 0 0-1h-2zm0 3a.5.5 0 0 0 0 1h2a.5.5 0 0 0 0-1h-2z" />
                            </svg>

                        </div>
                    </div>
                    <div class="col-md-8">
                        <div class="card-body">

                            <?= $form->field($model, 'id_persona')->hiddenInput(['value' => $persona->id_persona]) ?>

                            <h5><?= $persona->nombre . ' ' . $persona->otro_nombre . ' ' . $persona->apellido . " " . $persona->otro_apellido ?></h5>
                            <h5><?= $persona->tipoDocumento->nombre . ' ' . $persona->documento ?></h5>

                        </div>
                    </div>
                </div>
            </div>
            <div class="card">
                <div class="row no-gutters">
                    <div class="col-md-4">
                        <!--div class="card-header bg-soft-primary d-flex justify-content-center align-items-center ">

                            <svg fill="currentColor" version="1.1" id="Capa_1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" viewBox="0 0 489.7 489.7" xml:space="preserve" width="100">
                                <g>
                                    <g>
                                        <g>
                                            <rect x="0" width="58.3" height="489.7" />
                                            <rect x="432.2" y="281.8" width="57.5" height="207.9" />
                                            <path d="M185,287.7h224.6l0,0c0-39.3-31.9-70.7-70.7-70.7H185V287.7z" />
                                            <ellipse cx="119.8" cy="253.8" rx="33.8" ry="33.4" />
                                            <rect x="77.8" y="318.7" width="334.2" height="57.9" />
                                            <path d="M312.5,0H154v158.2h158.2V0H312.5z M266.7,93.7h-19v19c0,7.9-6.4,14.4-14.4,14.4l0,0c-7.9,0-14.4-6.4-14.4-14.4v-19h-19
                                                    c-7.9,0-14.4-6.4-14.4-14.4l0,0c0-7.9,6.4-14.4,14.4-14.4h19V45.5c0-7.9,6.4-14.4,14.4-14.4l0,0c7.9,0,14.4,6.4,14.4,14.4v19h19
                                                    c7.9,0,14.4,6.4,14.4,14.4v0.4C281,87.3,274.6,93.7,266.7,93.7z" />
                                        </g>
                                    </g>
                                </g>
                            </svg>
                        </div-->
                    </div>
                    <div class="col-md-8">
                        <div class="card-body mt-3">
                            
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <?php
                    $rrhh_Efector = new RrhhEfector();
                    $profesionales = $rrhh_Efector->obtenerMedicosPorEfector(yii::$app->user->getIdEfector());

                    echo $form->field($model, 'id_rrhh_asignado')->widget(Select2::classname(), [
                        'data' => ArrayHelper::map($profesionales, 'id', 'datos'),
                        'theme' => Select2::THEME_DEFAULT,
                        'language' => 'en',
                        'options' => ['placeholder' => 'Seleccione el Profesional...', 'class' => 'form-control'],
                        'pluginOptions' => [
                            'allowClear' => true,
                            'width' => '100%',
                        ],
                    ]);
                    ?>
                </div>                
                <div class="col-md-3">
                    <?= $form->field($model, 'fecha')->widget(DatePicker::className(), [
                        'type' => DatePicker::TYPE_COMPONENT_APPEND,
                        'pickerIcon' => '<i class="bi bi-calendar2-week"></i>',
                        'removeIcon' => '<i class="bi bi-trash"></i>',                        
                        'pluginOptions' => [
                           // 'value' => date("d/m/Y"),
                            'autoclose' => true
                        ]
                    ]) ?>
                </div>
                <div class="col-md-3">
                    <?= $form->field($model, 'hora')->widget(TimePicker::classname(), [
                        'pluginOptions' => [
                            'defaultTime' => date('H:i'),
                            'upArrowStyle' => 'bi bi-chevron-up',
                            'downArrowStyle' => 'bi bi-chevron-down',
                            'showMeridian' => false,
                        ],
                        'addon' => '<i class="bi bi-clock"></i>',
                    ]); ?>
                </div>
            </div>

        </div>
    </div>

    <div class="card">
        <div class="card-header bg-soft-info">
            <h5>Situación al Ingresar</h5>
        </div>
        <div class="card-body">
            
           <div class="row">
                <div class="col-sm-6">
                    <?= $form->field($model, 'ingresa_en')->radioList(Guardia::INGRESO_EN); ?>
                </div>
                <div class="col-sm-6">
                    <?= $form->field($model, 'ingresa_con')->radioList(Guardia::INGRESO_CON); ?>
                </div>
            </div>

            <div class="row border mt-3 mb-3" id="datos-acompañante" style="display: none">

                <div class="col-6">
                    <div class="row">
                        <h5 class="mt-3 mb-3">Datos del Acompañante</h5>

                        <div class="col">
                            <?= $form->field($model, 'datos_contacto_nombre')->textInput(['maxlength' => true]) ?>
                        </div>
                    </div>
                </div>

                <div class="col-6">
                    <div class="row">
                        <h5 class="mt-3 mb-3">Teléfono del Acompañante</h5>
                        <div class="col-sm-4">
                            <?php
                            echo $form->field($telefono, 'prefijo')->widget(Select2::classname(), [
                                'data' => Telefono::PREFIJO,
                                'theme' => Select2::THEME_DEFAULT,
                                'language' => 'en',
                                'value' => '+54',
                                'pluginOptions' => [
                                    'allowClear' => true,
                                    'width' => '100%',
                                ],
                            ]);
                            ?>
                        </div>
                        <div class="col-sm-4">
                            <?= $form->field($telefono, 'codArea')->textInput(['maxlength' => true]) ?>
                        </div>
                        <div class="col-sm-4">
                            <?= $form->field($telefono, 'numTelefono')->textInput(['maxlength' => true]) ?>
                        </div>
                    </div>
                </div>
            </div>
            <?= $form->field($model, 'situacion_al_ingresar')->textarea(['rows' => 4]) ?>
        </div>
    </div>

    <div class="card">
        <div class="card-header bg-soft-info">
            <h6>Obra Social</h6>
        </div>
        <div class="card-body">
          <?= $form->field($model, 'cobertura')->widget(Select2::classname(), [
                'data' => $coberturas,
                'theme' => Select2::THEME_DEFAULT,
                'language' => 'en',
                'options' => ['placeholder' => 'Seleccione cobertura social …',
                                'class' => 'form-control'],
                'pluginOptions' => [
                    'allowClear' => true
                ],
            ]);?>
        </div>
    </div>
   
    <div class="form-group">
        <?= Html::submitButton('Registrar Paciente', ['class' => 'btn btn-success']) ?>
    </div>

    <?php ActiveForm::end(); ?>

</div>
<?php
$this->registerJs("
$( document ).ready(function() {

    if('" . $model->ingresa_con . "' == 'familiar' || '" . $model->ingresa_con . "' == 'otro' || '" . $model->ingresa_con . "' == 'policia'){
        $('#datos-acompañante').show();
    }


    $('#i4').on('change', function() {
        if ($(this).is(':checked') ) {
            $('#datos-acompañante').show();
        } 
    });

    $('#i6').on('change', function() {
        if ($(this).is(':checked') ) {
            $('#datos-acompañante').show();
        } 
    });

    $('#i3').on('change', function() {
        if ($(this).is(':checked') ) {
            $('#datos-acompañante').hide();
        }
    });

    $('#i5').on('change', function() {
        if ($(this).is(':checked') ) {
            $('#datos-acompañante').show();
        }
    });

    $('#i7').on('change', function() {
        if ($(this).is(':checked') ) {
            $('#datos-acompañante').hide();
        }
    });
});");
?>