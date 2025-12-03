<?php

use yii\helpers\Html;
use yii\bootstrap5\ActiveForm;
use common\models\Efector;
use kartik\select2\Select2;
use yii\helpers\ArrayHelper;
use kartik\date\DatePicker;
use kartik\time\TimePicker;

/* @var $this yii\web\View */
/* @var $model common\models\Guardia */
/* @var $form yii\widgets\ActiveForm */
?>

<div class="guardia-form">

    <?php $form = ActiveForm::begin(); ?>

    <?= $form->errorSummary($model, ['class' => 'alert alert-danger', 'showAllErrors' => true]);  ?>

    <?= $form->field($model, 'id_persona')->hiddenInput()->label(false) ?>
    
    <?php // $form->field($model, 'updated_at')->textInput() ?>
    <div class="row">
        <div class="col-md-6">
            <?=
                $form->field($model, 'fecha_fin')->widget(DatePicker::className(), [
                'type' => DatePicker::TYPE_COMPONENT_APPEND,
                'pickerIcon' => '<i class="bi bi-calendar2-week"></i>',
                'removeIcon' => '<i class="bi bi-trash"></i>',
                'pluginOptions' => [
                    'autoclose' => true,
                    //'format' => 'dd-mm-yyyy'
                ]
              ]) 
            ?>
        </div>
        <div class="col-md-6">
            <?=
                $form->field($model, 'hora_fin')->widget(TimePicker::className(), [
                    'pluginOptions' => [
                        'defaultTime' => date('H:i'),
                        'showSeconds' => false,
                        'showMeridian' => false,
                        'minuteStep' => 15
                    ],
                ]);
            ?>
        </div>
    </div>                    

    <?php
        $efectores = Efector::find()->all();

        echo $form->field($model, 'id_efector_derivacion')->widget(Select2::classname(), [
            'data' => ArrayHelper::map($efectores, 'id_efector', 'nombre'),
            'theme' => 'default',
            'language' => 'en',
            'options' => ['placeholder' => 'Seleccione el lugar de derivación'],
            'pluginOptions' => [
                'allowClear' => true,
                //'dropdownParent' => '#modal-general'
            ],
        ]);
    ?>

    <?= $form->field($model, 'condiciones_derivacion')->textarea(['rows' => 6]) ?>

    <div class="form-group">
                        <label class="btn btn-outline-primary">
                            <input type="checkbox" id="notificar" autocomplete="off"> Solicitar internación
                        </label>
    </div>
    <div id="divNotificarEfector" style="display: none">
        <?php // $form->field($model, 'notificar_internacion_id_efector')->textInput() ?>
        <?php 
            echo $form->field($model, 'notificar_internacion_id_efector')->widget(Select2::classname(), [
                'data' => ArrayHelper::map($efectores, 'id_efector', 'nombre'),
                'theme' => 'default',
                'language' => 'en',
                'options' => ['placeholder' => 'Seleccione el lugar de derivación'],
                'pluginOptions' => [
                    'allowClear' => true,
                    //'dropdownParent' => '#modal-general'
                ],
            ]);
        ?>
    </div>
    <div class="form-group">
        <?= Html::submitButton('Finalizar', ['class' => 'btn btn-success']) ?>
    </div>

    <?php ActiveForm::end(); ?>

</div>
<?php
$this->registerJs("
$(document).ready(function(){
    $('#notificar').change(function(){
        $('#divNotificarEfector').toggle();
    });
});");