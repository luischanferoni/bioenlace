<?php

use yii\helpers\Html;
use yii\bootstrap5\ActiveForm;
use common\models\RrhhEfector;
use kartik\select2\Select2;
use yii\helpers\ArrayHelper;
use kartik\date\DatePicker;
use kartik\time\TimePicker;
use common\models\SegNivelInternacion;
use common\models\SegNivelInternacionTipoAlta;
use common\models\Efector;
use yii\web\JsExpression;

/* @var $this yii\web\View */
/* @var $model common\models\SegNivelInternacion */
/* @var $form yii\widgets\ActiveForm */
?>
<div class="seg-nivel-internacion-form">
    <?php $form = ActiveForm::begin([
        'enableClientValidation' => true,
        'id' => 'frm_internacion_alta']); ?>
    <?= $form->errorSummary($model); ?>

    <?= $form->field($model, 'fecha_fin')->widget(DatePicker::className(), [
        'type' => DatePicker::TYPE_COMPONENT_APPEND,
        'pickerIcon' => '<i class="bi bi-calendar2-week"></i>',
        'removeIcon' => '<i class="bi bi-trash"></i>',
        'pluginOptions' => [
            'autoclose' => true
        ]
    ]) ?>

    <?= $form->field($model, 'hora_fin')->widget(TimePicker::classname(), [
         'pluginOptions' => [
            'upArrowStyle' => 'bi bi-chevron-up', 
            'downArrowStyle' => 'bi bi-chevron-down',
            'showMeridian' => false,
        ],
        'addon' => '<i class="bi bi-clock"></i>',
    ]); ?>

    <?php
    $tipos_alta = SegNivelInternacionTipoAlta::find()->all();

    echo $form->field($model, 'id_tipo_alta')->widget(Select2::classname(), [
        'data' => ArrayHelper::map($tipos_alta, 'id', 'tipo_alta'),
        'theme' => Select2::THEME_DEFAULT,
        'language' => 'en',
        'options' => [
            'placeholder' => 'Seleccione el motivo del alta hospitalaria',
            'onchange' => '
                if ($("#segnivelinternacion-id_tipo_alta").val() == "5"){
                        $("#derivacionBox").show();                        
                    } else {                        
                         $("#derivacionBox").hide();
                }'
        ],
        'pluginOptions' => [
            'allowClear' => true,
            'dropdownParent' => $modal_id
        ],
    ]);
    ?>
    <div id="derivacionBox">
        <?php
        $efectores = Efector::find()->all();

        echo $form->field($model, 'id_efector_derivacion')->widget(Select2::classname(), [
            'data' => ArrayHelper::map($efectores, 'id_efector', 'nombre'),
            'theme' => Select2::THEME_DEFAULT,
            'language' => 'en',
            'options' => ['placeholder' => 'Seleccione el lugar de derivación'],
            'pluginOptions' => [
                'allowClear' => true,
                'dropdownParent' => $modal_id
            ],
        ]);
        ?>
        <?= $form->field($model, 'condiciones_derivacion')->textarea(['rows' => 3]) ?>
    </div>

    <?= $form->field($model, 'observaciones_alta')->textarea(['rows' => 6]) ?>
    <div class="form-group">
        <?= Html::submitButton('Guardar', [
            'class' => 'btn btn-success',
            'id' => 'mdl_alta_btn_submit']) ?>
    </div>

    <?php ActiveForm::end(); ?>

</div>

<?php
$const_tipo_alta_derivacion = SegNivelInternacion::TIPO_ALTA_DERIVACION_CMC;
$js = <<<EOJS
$( document ).ready(function() {
    var tipo_alta_derivacion = $const_tipo_alta_derivacion;
    var model_tipo_alta = '$model->id_tipo_alta';
    if( model_tipo_alta != tipo_alta_derivacion) {
        $('#derivacionBox').hide();
    }
});
EOJS;
$this->registerJs($js);