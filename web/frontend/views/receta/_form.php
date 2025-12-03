<?php

use yii\helpers\Html;
use yii\helpers\Url;
use yii\helpers\ArrayHelper;
use yii\bootstrap5\ActiveForm;
use yii\web\JsExpression;

use kartik\select2\Select2;
use kartik\date\DatePicker;
use wbraganca\dynamicform\DynamicFormWidget;

use common\models\Efector;
use common\models\Servicio;
use common\models\Condiciones_laborales;
use common\models\Agenda_rrhh;

/* @var $this yii\web\View */
/* @var $model common\models\RrhhEfector */
/* @var $form yii\widgets\ActiveForm */
?>

<?php
$form = ActiveForm::begin();
?>

<div class="card">
    <div class="card-header d-flex justify-content-between">
        <div class="header-title">
            <h4 class="card-title">Receta Digital Interoperable</h4>
        </div>
    </div>
    <div class="card-body" id="dynamic-servicio-form">
        <div class="container-items1">
            <div class="item1 row mb-3">
                <div class="col col-md-12">
                    <label>Paciente</label>
                    <?= Html::dropdownList(
                        'paciente',
                        null,
                        [0 => 'Mauro Sezella', 1 => 'Luis Chanferoni'],
                        ['class' => 'form-select']
                    ); ?>
                </div>
            </div>
            <div class="item1 row mb-3">
                <div class="col col-md-12">
                    <label>Diagnostico</label>
                    <?= Html::dropdownList(
                        'diagnostico',
                        null,
                        [
                            25064002 => 'cefalea',
                            195658003 => 'aringitis bacteriana aguda',
                            38341003 => 'hipertensión arterial'
                        ],
                        ['class' => 'form-select']
                    ); ?>
                </div>
            </div>
            <div class="row">
                <div class="col col-md-6">
                    <label>Medicamentos Genericos</label>
                    <?= Html::dropdownList(
                        'medicamento_generico',
                        null,
                        [
                            329653008 => 'ibuprofeno 400 mg por cada comprimido para administración oral',
                            374646004 => 'amoxicilina 500 mg por cada comprimido para administración oral',
                            318956006 => 'losartán potásico 50 mg por cada comprimido para administración oral',
                        ],
                        ['class' => 'form-select']
                    ); ?>
                </div>
                <div class="col col-md-6">
                    <label>Medicamentos Comerciales</label>
                    <?= Html::dropdownList(
                        'medicamento_comercial',
                        null,
                        [
                            134571000221104 => 'COPIRON 400 MG [IBUPROFENO 400 MG] COMPRIMIDO RECUBIERTO',
                            138041000221108 => 'DOLORSYN [IBUPROFENO 400 MG] COMPRIMIDO',
                            158001000221105 => 'ALMORSAN [AMOXICILINA TRIHIDRATO 500 MG] COMPRIMIDO RECUBIERTO',
                            131931000221108 => 'AMIXEN [AMOXICILINA 500 MG] COMPRIMIDO RECUBIERTO',
                            105961000221108 => 'COZAAREX [LOSARTAN POTASICO 50 MG] COMPRIMIDO',
                            130911000221107 => 'NITEN [LOSARTAN POTASICO 50 MG] COMPRIMIDO RECUBIERTO',
                        ],
                        ['class' => 'form-select']
                    ); ?>
                </div>
            </div>
            <div class="row mb-3">
                <div class="col col-md-12">
                    <label>Prescriptor</label>
                    <?= Html::dropdownList(
                        'medico',
                        null,
                        [
                            0 => 'Medico 1',
                            1 => 'Medico 2',
                        ],
                        ['class' => 'form-select']
                    ); ?>
                </div>
            </div>
            <div class="row mb-3">
                <h3>Indicaciones</h3>
                <div class="col col-md-12">
                    <textarea name="text" class="form-input"></textarea>
                </div>
            </div>
            <div class="row mb-3">
                <div class="col col-md-2">
                    <label>Duracion</label>
                    <?= Html::dropdownList(
                        'duracion',
                        null,
                        range(0, 7),
                        ['class' => 'form-select']
                    ); ?>
                </div>
                <div class="col col-md-2">
                    <label>Duracion Máxima</label>
                    <?= Html::dropdownList(
                        'duracion_maxima',
                        null,
                        range(0, 7),
                        ['class' => 'form-select']
                    ); ?>
                </div>
                <div class="col col-md-2">
                    <label>Unidad de Duración</label>
                    <?= Html::dropdownList(
                        'duracion_unidad',
                        null,
                        [
                            "d" => 'dia',
                            "s" => 'semana'
                        ],
                        ['class' => 'form-select']
                    ); ?>
                </div>

                <div class="col col-md-2">
                    <label>Frecuencia</label>
                    <?= Html::dropdownList(
                        'frecuencia',
                        null,
                        range(1, 7),
                        ['class' => 'form-select']
                    ); ?>
                </div>

                <div class="col col-md-2">
                    <label>Periodo</label>
                    <?= Html::dropdownList(
                        'periodo',
                        null,
                        range(1, 7),
                        ['class' => 'form-select']
                    ); ?>
                </div>

                <div class="col col-md-2">
                    <label>Unidad del Periodo</label>
                    <?= Html::dropdownList(
                        'periodo_unidad',
                        null,
                        [
                            "d" => 'dia',
                            "s" => 'semana'
                        ],
                        ['class' => 'form-select']
                    ); ?>
                </div>

                <div class="col col-md-12">
                <label>Unidad </label>
                    
                </div>

            </div>
            <div class="row mb-3">
                <div class="col col-md-12"><button type="submit" class="btn btn-info float-end">Guardar</button></div>
            </div>
        </div>
    </div>
</div>

<?php ActiveForm::end(); ?>