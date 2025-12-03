<?php

use common\models\ConsultaBalanceHidrico;
use yii\bootstrap5\ActiveForm;
use kartik\date\DatePicker;
use kartik\time\TimePicker;
use kartik\select2\Select2;

?>

<?php $form = ActiveForm::begin() ?>

<div class="card">

    <div class="card-header">

        <div class="header-title">
            <h4 class="card-title">Ficha de Balance Hidrico</h4>
        </div>

    </div>

    <div class="card-body">

        <div class="row">

            <div class="col-sm-2">
                <h6>Fecha</h6>

                <?= $form->field($modelBalanceHidrico, 'fecha')->widget(DatePicker::className(), [
                    'type' => DatePicker::TYPE_COMPONENT_APPEND,
                    'pickerIcon' => '<i class="bi bi-calendar2-week"></i>',
                    'removeIcon' => '<i class="bi bi-trash"></i>',
                    'pluginOptions' => [
                        'autoclose' => true
                    ]
                ])->label(false); ?>

            </div>

            <div class="col-sm-2">
                <h6>Hora desde</h6>

                <?= $form->field($modelBalanceHidrico, 'hora_desde')->widget(TimePicker::classname(), [
                    'pluginOptions' => [
                        'upArrowStyle' => 'bi bi-chevron-up',
                        'downArrowStyle' => 'bi bi-chevron-down',
                        'showMeridian' => false,
                    ],
                    'addon' => '<i class="bi bi-clock"></i>',
                ])->label(false); ?>
            </div>

            <div class="col-sm-2">
                <h6>Hora hasta</h6>

                <?= $form->field($modelBalanceHidrico, 'hora_hasta')->widget(TimePicker::classname(), [
                    'pluginOptions' => [
                        'upArrowStyle' => 'bi bi-chevron-up',
                        'downArrowStyle' => 'bi bi-chevron-down',
                        'showMeridian' => false,
                    ],
                    'addon' => '<i class="bi bi-clock"></i>',
                ])->label(false); ?>
            </div>

        </div>

        <div class="row">

            <h5 class="mb-5">Ingresos (Via Parenteral)</h5>

            <div class="col-sm-2">
                <h6>Tipo de solución</h6>
                <?= $form->field($modelBalanceHidrico, 'tipo_solucion')->textInput(['type' => 'number','placeholder' => "En cc"])->label(false); ?>
            </div>

            <div class="col-sm-2">
                <h6>Cantidad de Paso</h6>
                <?= $form->field($modelBalanceHidrico, 'cant_paso')->textInput(['type' => 'number','placeholder' => "En cc"])->label(false); ?>
            </div>

        </div>

        <div class="row mt-3">

            <h5 class="mb-5">Egresos</h5>

            <div class="col-sm-2">
                <h6>Deposiciones</h6>
                <?= $form->field($modelBalanceHidrico, 'deposiciones')->textInput(['type' => 'number','placeholder' => "En cc"])->label(false); ?>
            </div>

            <div class="col-sm-2">
                <h6>Tipo de deposiciones</h6>
                <?=  $form->field($modelBalanceHidrico, 'tipo_deposicion')->widget(Select2::classname(), [
                        'data' => ConsultaBalanceHidrico::TIPO_DEPOSICION,
                        'theme' => Select2::THEME_DEFAULT,
                        'language' => 'en',
                        'options' => ['placeholder' => 'Seleccione un tipo'],
                        'pluginOptions' => [
                            'allowClear' => true
                        ],
                    ])->label(false); ?>
            </div>


            <div class="col-sm-2">
                <h6>Diuresis</h6>
                <?= $form->field($modelBalanceHidrico, 'diuresis')->textInput(['type' => 'number','placeholder' => "En cc"])->label(false); ?>
            </div>

            <div class="col-sm-2">
                <h6>Tipo de diuresis</h6>
                <?=  $form->field($modelBalanceHidrico, 'tipo_diuresis')->widget(Select2::classname(), [
                        'data' => ConsultaBalanceHidrico::TIPO_DIURESIS,
                        'theme' => Select2::THEME_DEFAULT,
                        'language' => 'en',
                        'options' => ['placeholder' => 'Seleccione un tipo'],
                        'pluginOptions' => [
                            'allowClear' => true
                        ],
                    ])->label(false); ?>
            </div>

            <div class="col-sm-2">
                <h6>Vomitos</h6>
                <?= $form->field($modelBalanceHidrico, 'vomitos')->textInput(['type' => 'number','placeholder' => "En cc"])->label(false); ?>
            </div>

            <div class="col-sm-2">
                <h6>S.N.G</h6>
                <?= $form->field($modelBalanceHidrico, 'sng')->textInput(['type' => 'number','placeholder' => "En cc"])->label(false); ?>
            </div>

            <div class="col-sm-2">
                <h6>Drenaje N° 1</h6>
                <?= $form->field($modelBalanceHidrico, 'drenaje_1')->textInput([])->label(false); ?>
            </div>

            <div class="col-sm-2">
                <h6>Drenaje N° 2</h6>
                <?= $form->field($modelBalanceHidrico, 'drenaje_2')->textInput([])->label(false); ?>
            </div>

            <div class="col-sm-2">
                <h6>Drenaje N° 3</h6>
                <?= $form->field($modelBalanceHidrico, 'drenaje_3')->textInput([])->label(false); ?>
            </div>

            <div class="col-sm-2">
                <h6>Drenaje N° 4</h6>
                <?= $form->field($modelBalanceHidrico, 'drenaje_4')->textInput([])->label(false); ?>
            </div>



        </div>



    </div>



</div>


<?php ActiveForm::end(); ?>