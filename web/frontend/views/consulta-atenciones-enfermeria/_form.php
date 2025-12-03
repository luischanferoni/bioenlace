<?php

use yii\helpers\Html;
use yii\bootstrap5\ActiveForm;
use yii\helpers\ArrayHelper;
use yii\web\JsExpression;
use kartik\date\DatePicker;
use kartik\select2\Select2;
use yii\helpers\Url;

/* @var $this yii\web\View */
/* @var $model common\models\Barrios */
/* @var $form yii\widgets\ActiveForm */
?>

<div class="atenciones-enfermeria-form">

    <?php $form = ActiveForm::begin((['id' => 'atenciones-enfermeria'])); ?>

    <?= $form->errorSummary($modelConsulta, ['class' => 'alert alert-danger', 'showAllErrors' => true]);  ?>
    <?= $form->errorSummary($model, ['class' => 'alert alert-danger', 'showAllErrors' => true]);  ?>

    <?php $datos = json_decode($model->datos, true);
    ?>

    <div class="row">
        <div class="col-lg-3 col-md-6 col-sm-12 col-xs-12">
            <div class="card mb-3 bg-soft-primary">
                <div class="card-title mt-3 mb-3 border-bottom">
                    <h5 class="text-center">Control Tensión Arterial #1</h5>
                </div>
                <div class="card-text">
                    <div class="row mb-5">
                        <div class="col-md-6 col-sm-12 col-xs-12 ps-5">
                            <?php /* sistolica == 271649006 */ ?>
                            <?= Html::label('Sistólica', 'TensionArterial1[271649006]', ['class' => 'control-label']) ?>
                            <?= Html::input('text', 'TensionArterial1[271649006]', isset($datos['TensionArterial1'][271649006]) ? $datos['TensionArterial1'][271649006] : '', ['class' => 'form-control', 'placeHolder' => 'Ej. 110']) ?>
                            <!-- <div class="input-group"></div> -->
                        </div>
                        <div class="col-md-6 col-sm-12 col-xs-12 pe-5">
                            <?php /* diastolica == 271650006 */ ?>
                            <?= Html::label('Diastólica', 'TensionArterial1[271650006]', ['class' => 'control-label']) ?>
                            <?= Html::input('text', 'TensionArterial1[271650006]', isset($datos['TensionArterial1'][271650006]) ? $datos['TensionArterial1'][271650006] : '', ['class' => 'form-control', 'placeHolder' => 'Ej. 070']) ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 col-sm-12 col-xs-12">
            <div class="card mb-3 bg-soft-primary">
                <div class="card-title mt-3 mb-3 border-bottom">
                    <h5 class="text-center">Control Tensión Arterial #2</h5>
                </div>

                <div class="card-text">
                    <div class="row mb-5">
                        <div class="col-md-6 col-sm-12 col-xs-12 ps-5">
                            <?= Html::label('Sistólica', 'TensionArterial2[271649006]', ['class' => 'control-label']) ?>
                            <?= Html::input('text', 'TensionArterial2[271649006]', isset($datos['TensionArterial2'][271649006]) ? $datos['TensionArterial2'][271649006] : '', ['class' => 'form-control', 'placeHolder' => 'Ej. 110']) ?>
                        </div>
                        <div class="col-md-6 col-sm-12 col-xs-12 pe-5">
                            <?= Html::label('Diastólica', 'TensionArterial2[271650006]', ['class' => 'control-label']) ?>
                            <?= Html::input('text', 'TensionArterial2[271650006]', isset($datos['TensionArterial2'][271650006]) ? $datos['TensionArterial2'][271650006] : '', ['class' => 'form-control', 'placeHolder' => 'Ej. 070']) ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 col-sm-12 col-xs-12">
            <div class="card mb-3 bg-soft-primary">
                <div class="card-title mt-3 mb-3 border-bottom">
                    <h5 class="text-center">Control Peso / Talla</h5>
                </div>
                <div class="card-text">
                    <div class="row mb-5">
                        <div class="col-md-6 col-sm-12 col-xs-12 ps-5">
                            <?php /* peso == 162879003 */ ?>
                            <?= Html::label('Peso', '162879003p', ['class' => 'control-label']) ?>
                            <?= Html::input('text', '162879003p', isset($datos['162879003p']) ? $datos['162879003p'] : '', ['class' => 'form-control', 'placeHolder' => 'En kg.']) ?>
                        </div>
                        <div class="col-md-6 col-sm-12 col-xs-12 pe-5">
                            <?php /* talla == 162879003 */ ?>
                            <?= Html::label('Talla', '162879003t', ['class' => 'control-label']) ?>
                            <?= Html::input('text', '162879003t', isset($datos['162879003t']) ? $datos['162879003t'] : '', ['class' => 'form-control', 'placeHolder' => 'En cm.']) ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 col-sm-12 col-xs-12">
            <div class="card mb-3 bg-soft-primary">
                <div class="card-title mt-3 mb-3 border-bottom">
                    <h5 class="text-center">Control Agudeza Visual</h5>
                </div>

                <div class="card-text">
                    <div class="row mb-5">
                    <div class="col-md-6 col-sm-12 col-xs-12 ps-5">
                        <div class="input-group">
                            <?= Html::label('Ojo Izquierdo', '386708005', ['class' => 'control-label']) ?>
                            <?= Html::input('text', '386708005', isset($datos['386708005']) ? $datos['386708005'] : '', ['class' => 'form-control']) ?>
                            <span class="input-group-text" id="basic-addon2">/10</span>                            
                        </div>
                        </div>
                        <div class="col-md-6 col-sm-12 col-xs-12 pe-5">
                            <div class="input-group">
                                <?= Html::label('Ojo Derecho', '386709002', ['class' => 'control-label']) ?>
                                <?= Html::input('text', '386709002', isset($datos['386709002']) ? $datos['386709002'] : '', ['class' => 'form-control']) ?>
                                <span class="input-group-text" id="basic-addon2">/10</span>   
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-3 col-md-6 col-sm-4 col-xs-4">
            <div class="card mb-3 bg-soft-info">
                <div class="card-title mt-3 mb-3 border-bottom">
                    <h5 class="text-center">Control Temperatura</h5>
                </div>
                <div class="card-text">
                    <div class="row mb-5 ps-3 pe-3">
                        <div class="col-md-12 col-sm-12 col-xs-12">
                            <?php /* temperatura == 703421000 */ ?>
                            <?= Html::label('Temperatura', '703421000', ['class' => 'control-label']) ?>
                            <?= Html::input('text', '703421000', isset($datos['703421000']) ? $datos['703421000'] : '', ['class' => 'form-control']) ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 col-sm-4 col-xs-4">
            <div class="card mb-3 bg-soft-info">
                <div class="card-title mt-3 mb-3 border-bottom">
                    <h5 class="text-center">Control Glucemia Capilar</h5>
                </div>
                <div class="card-text">
                    <div class="row mb-5 ps-3 pe-3">
                        <div class="col-md-12 col-sm-12 col-xs-12">
                            <?= Html::label('Glucemia Capilar', '434912009', ['class' => 'control-label']) ?>
                            <?= Html::input('text', '434912009', isset($datos['434912009']) ? $datos['434912009'] : '', ['class' => 'form-control']) ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 col-sm-4 col-xs-4">
            <div class="card mb-3 bg-soft-info">
                <div class="card-title mt-3 mb-3 border-bottom">
                    <h5 class="text-center">Control C. A.</h5>
                </div>
                <div class="card-text">
                    <div class="row mb-5 ps-3 pe-3">
                        <div class="col-md-12 col-sm-12 col-xs-12">
                            <?= Html::label('Circunferencia Abdominal', '396552003', ['class' => 'control-label']) ?>
                            <?= Html::input('text', '396552003', isset($datos['396552003']) ? $datos['396552003'] : '', ['class' => 'form-control', 'placeHolder' => 'En cm.']) ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 col-sm-4 col-xs-4">
            <div class="card mb-3 bg-soft-info">
                <div class="card-title mt-3 mb-3 border-bottom">
                    <h5 class="text-center">Control P.C.</h5>
                </div>
                <div class="card-text">
                    <div class="row mb-5 ps-3 pe-3">
                        <div class="col-md-12 col-sm-12 col-xs-12">
                            <?= Html::label('Perimetro Cefalico', '363812007', ['class' => 'control-label']) ?>
                            <?= Html::input('text', '363812007', isset($datos['363812007']) ? $datos['363812007'] : '', ['class' => 'form-control', 'placeHolder' => 'En cm.']) ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4 col-md-6 col-sm-12 col-xs-12">
            <div class="card mb-3 bg-soft-primary">

                <div class="card-title mt-3 mb-3 border-bottom">
                    <h5 class="text-center">Frecuencia Cardíaca</h5>
                </div>

                <div class="card-text">
                    <div class="row mb-5 ps-3 pe-3">
                        <div class="col-md-12 col-sm-12 col-xs-12">
                            <?= Html::label('', '364075005', ['class' => 'control-label']) ?>
                            <?= Html::input('text', '364075005', isset($datos['364075005']) ? $datos['364075005'] : '', ['class' => 'form-control', 'placeHolder' => '0-300']) ?>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <div class="col-lg-4 col-md-6 col-sm-12 col-xs-12">
            <div class="card mb-3 bg-soft-primary">

                <div class="card-title mt-3 mb-3 border-bottom">
                    <h5 class="text-center">Frecuencia Respiratioria</h5>
                </div>

                <div class="card-text">
                    <div class="row mb-5 ps-3 pe-3">
                        <div class="col-md-12 col-sm-12 col-xs-12">
                            <?= Html::label('', '86290005', ['class' => 'control-label']) ?>
                            <?= Html::input('text', '86290005', isset($datos['86290005']) ? $datos['86290005'] : '', ['class' => 'form-control', 'placeHolder' => '1-60']) ?>
                        </div>
                    </div>
                </div>

            </div>

        </div>

        <div class="col-lg-4 col-md-6 col-sm-12 col-xs-12">
            <div class="card mb-3 bg-soft-primary">
                <div class="card-title mt-3 mb-3 border-bottom">
                    <h5 class="text-center">Saturación de Oxigeno</h5>
                </div>
                <div class="card-text">
                    <div class="row mb-5 ps-3 pe-3">
                        <div class="col-md-12 col-sm-12 col-xs-12">
                            <?= Html::label('', '103228002', ['class' => 'control-label']); ?>
                            <?= Html::input('text', '103228002', isset($datos['103228002']) ? $datos['103228002'] : '', ['class' => 'form-control', 'placeHolder' => '0-99']) ?>
                        </div>
                    </div>
                </div>

            </div>

        </div>



    </div>

    <?php
    switch (Yii::$app->user->getEncounterClass()) {
        case 'IMP':
            echo $this->render('_controles_internacion', [
                'model' => $model,
                'form' => $form
            ]);
            break;

        default:
            echo $this->render('_otros_controles', [
                'model' => $model,
                'form' => $form,
                'datos' => $datos
            ]);
            break;
    }
    ?>

    <hr class="border border-info border-1 opacity-50">
    <?= Html::submitButton($modelConsulta->urlSiguiente == 'fin' ? 'Finalizar' : 'Guardar y Continuar', ['class' => 'btn btn-primary rounded-pill float-end']) ?>

    <?php ActiveForm::end(); ?>

</div>

<?php
$headerMenu = $modelConsulta->getHeader();
$header = "$('#modal-consulta-label').html('" . $headerMenu . "')";
$this->registerJs($header);
?>