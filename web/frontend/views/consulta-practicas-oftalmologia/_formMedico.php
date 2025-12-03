<?php

use yii\helpers\Html;
use yii\bootstrap5\ActiveForm;
use yii\web\JqueryAsset;
use wbraganca\dynamicform\DynamicFormWidget;
use common\models\ConsultaPracticasOftalmologia;

use common\assets\SisseDynamicFormAsset;


SisseDynamicFormAsset::register($this);

$form = ActiveForm::begin(['id' => 'form_oftalmologia']);


if($form_steps) {
  echo Html::hiddenInput('form_steps', 1);
} 

DynamicFormWidget::begin([
    'widgetContainer' => 'ofta_dform_wrapper', // required: only alphanumeric characters plus "_" [A-Za-z0-9_]
    'widgetBody' => '.container-items', // required: css class selector
    'widgetItem' => '.item', // required: css class
    'limit' => 10, // the maximum times, an element can be cloned (default 999)
    'min' => $form_childs_min, // 0 or 1 (default 1)
    'insertButton' => '.add-item', // css class
    'deleteButton' => '.remove-item', // css class
    'model' => $oftalmologias[0],
    'formId' => 'form_oftalmologia',
    'formFields' => [
        'codigo',
        'ojo',
        'resultado',
        'informe',
    ],
]);
?>
<div class="container-items">
    <?php foreach ($oftalmologias as $i => $model) : ?>
    <div class="item">
        <div class="row">
            <div class="col-sm-4">
                <?= $form->field($model, "[{$i}]codigo")
                        ->dropDownList(
                            ConsultaPracticasOftalmologia::PRACTICAS_OFTALMOLOGICAS, 
                            ['prompt' => 'Seleccione Uno',
                                'style' => 'width:265px !important',
                                'class'=> 'form-control field_codigo' ]
                        ) ?>
                <input type="checkbox" id="no-evaluar" name="no-evaluar"/>No se puede evaluar
            </div>
            <div class="col-sm-3">
                <?= $form->field($model, "[{$i}]ojo")
                        ->dropDownList(
                            $arrayO, 
                            ['prompt' => 'Seleccione un Ojo']
                        ) ?>
            </div>

            <div class="col-md-3">
                <?= $form->field(
                        $model,
                        "[{$i}]resultado", 
                        ['options' => ['class' => 'input_resultado']]
                        )->textInput(['type' => 'string']) ?>
                <?= $form->field(
                        $model, 
                        "[{$i}]informe", 
                        ['options' => ['class' => 'input_informe']]
                        )->textarea(['rows' => 6]) ?>
            </div>

            <div class="col-sm-1">
                <a class="remove-item text-warning" href="#" data-bs-toggle="tooltip" data-bs-placement="right" data-bs-original-title="Quitar esta fila">
                    <?= $this->render('../site/svg_icon_remove.php'); ?>
                </a>
            </div>

            <hr class="border border-light border-1 opacity-50">
        </div>
    </div>
    <?php endforeach; ?>
</div>
<div class="row pe-2">
    <div class="col-xs-12">
        <button type="button" class="add-item mt-1 btn btn-info rounded-pill float-xl-end text-white">
            <i class="bi bi-plus-circle text-white"></i> Agregar Practica Oftalmológica
        </button>
    </div>
</div>
<?php DynamicFormWidget::end(); ?>

<hr class="border border-info border-1 opacity-50">
<?php if($form_steps): ?>
    <?php if ($modelConsulta->urlAnterior) { ?>
        <?= Html::a(
              'Anterior', 
              $modelConsulta->urlAnterior,
              ['class' => 
                'btn btn-primary atender rounded-pill float-start']) ?>
    <?php } ?>
    <?= Html::submitButton(
          $modelConsulta->urlSiguiente == 'fin' ? 'Finalizar' : 'Guardar y Continuar',
          ['class' => 'btn btn-primary rounded-pill float-end']) ?>
    <?php
    $headerMenu = $modelConsulta->getHeader();
    $header = "$('#modal-consulta-label').html('".$headerMenu."')";
    $this->registerJs($header);
    ?>
<?php else:?>
  <div class="form-group">
  <?= Html::submitButton('Guardar', ['class' => 'btn btn-success']) ?>
  <?= Html::a(
              'Cancelar',
              ['/consulta-practicas-oftalmologia'],
              ['class' => 'btn btn-danger', 'role' => 'button']) ?>
  </div>
<?php endif;?>

<?php ActiveForm::end(); ?>
<?php
$script = <<<JS

function codigo_changed(
    field_codigo,
    input_informe,
    input_resultado
) {
    if(field_codigo.val() == '') {
            input_informe.hide();
            input_resultado.hide();
            //input_cod_egreso.val("");
    }else if(field_codigo.val() == 164729009){
        input_informe.hide();
        input_resultado.show();
    }else{
       input_informe.show();
       input_resultado.hide();
    }
}
        
function inicializar_filas() {
    console.log("inicializar_filas");
    $(".item").each(function (index, element) {
      var field_codigo = $(element).find(".field_codigo");
      var input_informe = $(element).find(".input_informe");
      var input_resultado = $(element).find(".input_resultado");

      codigo_changed(
          field_codigo, 
          input_informe,
          input_resultado,
      );
      
      field_codigo.off('change');
      field_codigo.on('change', function() {
          codigo_changed(
              field_codigo, 
              input_informe,
              input_resultado,
          );
      });
    });
};

$( document ).ready(function() {
    inicializar_filas();
    
    $(".ofta_dform_wrapper").on("afterInsert", function(e, item) {
        console.log("on_after_insert");
        inicializar_filas();
    });
});
JS;

$this->registerJs($script);
?>
<style type="text/css">

    svg {
        fill: none;
    }
</style>