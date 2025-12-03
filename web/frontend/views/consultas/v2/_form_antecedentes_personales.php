<?php

use yii\helpers\Url;
use yii\helpers\Html;
use yii\web\JsExpression;
use yii\helpers\ArrayHelper;
use yii\web\View;
use yii\bootstrap5\ActiveForm;

use kartik\select2\Select2;

use frontend\assets\FormWizardAsset;

//FormWizardAsset::register($this);
?>

<?php $form = ActiveForm::begin([
    'id' => 'form-sintomas',
    //  'enableClientValidation' => false,
    'options' => ['class' => 'form-wizard']
]);
?>
    <?= $form->errorSummary($modelConsulta, ['class' => 'alert alert-danger', 'showAllErrors' => true]);  ?>
    <?= $form->errorSummary($modelPersonasAntecedenteConsultas, ['class' => 'alert alert-danger', 'showAllErrors' => true]);  ?>

    <div class="row">       
        <div class="col-lg-12">
            <div class="form-group">
                <?=
                    $form->field($modelPersonasAntecedenteConsultas, "select2_codigo")->widget(Select2::classname(), [
                        'data' => [$dataAntecedentesPersonales],
                        'size' => Select2::LARGE,
                        'theme'=>'default',
                        'options' => [
                            'placeholder' => '- Escriba los Hallazgos -', 
                            'multiple' => 'true', 
                            'id' => 'select_hallazgos',                                     
                            //'value' => $text
                        ],
                        'pluginOptions' => [
                            'initialize' => true,
                            'minimumInputLength' => 4,
                            'dropdownParent' => '#modal-consulta',
                            'width' => '100%',                                    
                            'ajax' => [
                                'url' => Url::to(['snowstorm/antecedentespersonales']),
                                'dataType' => 'json',
                                'delay'=> 500,
                                'data' => new JsExpression('function(params) { return {q:params.term}; }'),
                                'cache' => true
                            ],
                        ],                            
                        'pluginEvents' => [
                            "select2:select select2:unselect" => 'function() {
                                let i = 0;
                                let hallazgosSeleccionados = [];
                                datos = $(this).select2("data");
                                while (i < datos.length) {
                                    hallazgosSeleccionados.push(datos[i].text);
                                    i++;
                                }

                                $("#terminos_situaciones_personales").val(hallazgosSeleccionados.join());
                            }',
                        ]
                    ])->label(false);
                ?>
                <?= Html::hiddenInput(
                    "terminos_situaciones_personales",
                    implode(",", array_values($dataAntecedentesPersonales)),
                    ['id' => "terminos_situaciones_personales"]
                );
                ?>
            </div>

            <h5 class="mb-3 ms-1 text-decoration-underline">Antecedentes Personales (Cargados Previamente)</h5>
                <p class="mb-3 ms-1">
                    <?php if (count($antecedentesPersonales) == 0) {
                        echo '<span class="ms-2">Sin datos</span>';
                    } ?>
                    <?php foreach ($antecedentesPersonales as $antecedente) { ?>
                        <span class="badge border border-gray text-gray me-2 mb-2"><?= strtoupper($antecedente->snomedSituacion->term) ?></span>
                    <?php } ?>
                </p>
        </div>
    </div>

    <hr class="border border-info border-1 opacity-50">
    <?php if ($modelConsulta->urlAnterior) { ?>
        <?= Html::a('Anterior', $modelConsulta->urlAnterior, ['class' => 'btn btn-primary atender rounded-pill float-start']) ?>
    <?php } ?>
    <?= Html::submitButton($modelConsulta->urlSiguiente == 'fin' ? 'Finalizar' : 'Guardar y/o Continuar', ['class' => 'btn btn-primary rounded-pill float-end']) ?>

<?php ActiveForm::end(); ?>

<?php
$headerMenu = $modelConsulta->getHeader();
$header = "$('#modal-consulta-label').html('".$headerMenu."')";
$this->registerJs($header);
?>