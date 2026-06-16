<?php

use yii\helpers\Html;
use yii\bootstrap5\ActiveForm;
use kartik\date\DatePicker;

/* @var $this yii\web\View */
/* @var $model common\models\AgendaFeriados */
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
<div class="agenda-feriados-form">

    <?php $form = ActiveForm::begin(); ?>

    <div class="card">

        <div class="card-header bg-soft-info">
            <h3>Crear Nuevo Feriado</h3>
        </div>
        <div class="card-body">
            <div class="row justify-content-center">

                <div class="col-md-6">

                    <?= $form->field($model, 'titulo')->textInput() ?>

                    <?= $form->field($model, 'fecha')->widget(DatePicker::className(), [
                        'type' => DatePicker::TYPE_COMPONENT_APPEND,
                        'pickerIcon' => '<i class="bi bi-calendar2-week"></i>',
                        'removeIcon' => '<i class="bi bi-trash"></i>',
                        'pluginOptions' => [
                            'autoclose' => true
                        ]
                    ]) ?>

                    <?= $form->field($model, 'repite_todos_anios')->radioList(['SI' => 'Si', 'NO' => 'No'], ['prompt' => '']) ?>

                    <?= $form->field($model, 'horario')->radioList(['TODO_EL_DIA' => 'Todo el día', 'HASTA_MEDIODIA' => 'Hasta medio día', 'DESDE_MEDIODIA' => 'Desde medio día'], ['prompt' => '']); ?>

                    <div class="form-group">
                        <?= Html::submitButton('Guardar', ['class' => 'btn btn-success']) ?>
                    </div>
                </div>

            </div>
        </div>

    </div>

    <?php ActiveForm::end(); ?>

</div>