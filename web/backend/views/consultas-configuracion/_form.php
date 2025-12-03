<?php

use yii\helpers\Html;
use yii\helpers\Url;
use yii\web\JsExpression;
use yii\widgets\ActiveForm;

use kartik\select2\Select2;

use common\models\ConsultasConfiguracion;
/* @var $this yii\web\View */
/* @var $model app\models\ConsultasConfiguracion */
/* @var $form yii\widgets\ActiveForm */
?>

<?php $form = ActiveForm::begin(); ?>

    <?= 
        $form->field($model, "id_servicio")->widget(Select2::classname(), [
            'size' => Select2::LARGE,
            'options' => ['placeholder' => '- Servicio -'],
            'theme' => 'default',
            'pluginOptions' => [
                'minimumInputLength' => 3,
                'ajax' => [
                    'url' => Url::to(['servicios/search']),
                    'dataType' => 'json',
                    'data' => new JsExpression('function(params) { return {q:params.term}; }'),
                    'cache' => true
                ],
            ],
        ])->label(false);
    ?>

    <div class="col-md-2 col-sm-12 col-xs-12">
        <?= $form->field($model, 'encounter_class', ['labelOptions' =>  ['class' => 'control-label']])->dropDownList(ConsultasConfiguracion::ENCOUNTER_CLASS, ['prompt' => '']); ?>
    </div>

    <?= $form->field($model, 'pasos_json')->textarea(['rows' => 10]) ?>

    <div id="resultado_urls"></div>
    <div class="form-group">
        <?= Html::submitButton('Guardar', ['class' => 'btn btn-success float-end']) ?>
    </div>
<?php ActiveForm::end(); ?>

<?php
    $this->registerJs("
    $(document).on('change', '#consultasconfiguracion-pasos', function() {
        $.ajax({
            url: '". Url::to(['consultas-configuracion/checkear-urls']) ."',
            type: 'POST',
            data: {pasos: $(this).val()},
            success: function (data) {
                $('#resultado_urls').html(data);
            },
            error: function () {
                $('body').append('<div class=\"alert alert-success\" role=\"alert\">'
                    +'<i class=\"fa fa-exclamation fa-1x\"></i> Error inesperado</div>'); 
                window.setTimeout(function() { $('.alert').alert('close'); }, 6000);
            }
        });
    });
    ",
    yii\web\View::POS_READY);

