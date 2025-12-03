<?php

use yii\helpers\Url;
use yii\helpers\Html;
use yii\web\JsExpression;
use yii\helpers\ArrayHelper;
use yii\web\View;
use yii\bootstrap5\ActiveForm;

use kartik\select2\Select2;

use common\models\ConsultaSintomas;
use frontend\assets\FormWizardAsset;

//FormWizardAsset::register($this);
?>

<?php $form = ActiveForm::begin([
    'id' => 'form-sintomas',
    //  'enableClientValidation' => false,
    'options' => ['class' => 'form-wizard']
]);
?>

<!------ Formulario Síntomas ------->
<div class="form-card card mb-3 border rounded border-0">
    <div class="card-header d-flex justify-content-between">
        <div class="header-title">
            <h4 class="card-title">Síntomas</h4>
        </div>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-lg-12">
                <div class="form-group">
                    <?=
                    $form->field($modelConsultaSintomas, "select2_codigo")->widget(Select2::classname(), [
                        'data' => [$dataSintomas],
                        'size' => Select2::LARGE,
                        'theme' => 'default',
                        'options' => [
                            'placeholder' => '- Escriba los Síntomas -',
                            'multiple' => 'true',
                            'id' => 'select_sintomas',
                            //'value' => $text
                        ],
                        'pluginOptions' => [
                            'initialize' => true,
                            'minimumInputLength' => 4,
                            'dropdownParent' => '#modal-consulta',
                            'width' => '100%',
                            'ajax' => [
                                'url' => Url::to(['snowstorm/sintomas']),
                                'dataType' => 'json',
                                'delay'=> 500,
                                'data' => new JsExpression('function(params) { return {q:params.term}; }'),
                                'cache' => true
                            ],
                        ],
                        'pluginEvents' => [
                            "select2:select select2:unselect" => 'function() {
                                        let i = 0;
                                        let sintomasSeleccionados = [];
                                        datos = $(this).select2("data");
                                        while (i < datos.length) {
                                            sintomasSeleccionados.push(datos[i].text);
                                            i++;
                                        }

                                        $("#terminos_sintomas").val(sintomasSeleccionados.join());
                                    }',
                        ]
                    ])->label(false);
                    ?>
                    <?= Html::hiddenInput(
                        "terminos_sintomas",
                        implode(",", array_values($dataSintomas)),
                        ['id' => "terminos_sintomas"]
                    );
                    ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?= Html::submitButton($modelConsulta->urlSiguiente == 'fin' ? 'Finalizar' : 'Siguiente', ['class' => 'btn btn-primary rounded-pill float-end']) ?>

<?php ActiveForm::end(); ?>

<?php
$headerMenu = $modelConsulta->getHeader();
$header = "$('#modal-consulta-label').html('".$headerMenu."')";
$this->registerJs($header);
?>