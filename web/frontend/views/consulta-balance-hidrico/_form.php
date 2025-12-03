<?php

use yii\helpers\Html;
use yii\bootstrap5\ActiveForm;
use kartik\select2\Select2;
use kartik\date\DatePicker;
use kartik\time\TimePicker;
use yii\helpers\ArrayHelper;

use wbraganca\dynamicform\DynamicFormWidget;
##use common\assets\SisseDynamicFormAsset;
use common\models\ConsultaBalanceHidrico;


#DynamicFormWidget::register($this);

/* @var $this yii\web\View */
/* @var $model common\models\SegNivelInternacionBalancehidrico */
/* @var $form yii\widgets\ActiveForm */
?>

<?php 
$form = ActiveForm::begin(['id' => 'form_balances']);
if($is_ajax) {
  echo Html::hiddenInput('from_step_forms', 1);
} 

  DynamicFormWidget::begin([
      'widgetContainer' => 'balances_dform_wrapper', // required: only alphanumeric characters plus "_" [A-Za-z0-9_]
      'widgetBody' => '.container-items', // required: css class selector
      'widgetItem' => '.item', // required: css class
      'limit' => 10, // the maximum times, an element can be cloned (default 999)
      'min' => 0, // 0 or 1 (default 1)
      'insertButton' => '.add-item', // css class
      'deleteButton' => '.remove-item', // css class
      'model' => $balances[0],
      'formId' => 'form_balances',
      'formFields' => [
          'tipo_registro',
          'cod_ingreso',
          'cod_egreso',
          'hora_inicio',
          'hora_fin',
          'cantidad',
      ],
  ]);
  ?>

<div class="container-items">
  <?php foreach ($balances as $i => $model) : ?>
    <div class="item">
      <div class="row">
            <div class="col-3">
              <?= $form->field($model, "[{$i}]fecha")
                      ->widget(
                          DatePicker::className(), [
                              'type' => DatePicker::TYPE_COMPONENT_APPEND,
                              'pickerIcon' => '<i class="bi bi-calendar2-week"></i>',
                              'removeIcon' => '<i class="bi bi-trash"></i>',
                              'pluginOptions' => [
                                  'autoclose' => true,
                                  'format' => "dd/mm/yyyy",
                                  ],
                              ]
                      ) ?>
            </div>
            <div class="col-2">
              <?= $form->field($model, "[{$i}]tipo_registro")
                      ->dropDownList(
                          [ 'Ingreso' => 'Ingreso', 'Egreso' => 'Egreso', ],
                          ['prompt' => '',
                           'class'=> 'form-control field_tipo_registro']) ?>
            </div>
            <div class="col-md-3">
              <?= $form->field(
                      $model,
                      "[{$i}]cod_ingreso",
                      ['options' => ['class' => 'field_cod_ingreso']])
                      ->dropDownList(
                          ConsultaBalanceHidrico::$tipos_cod_ingreso,
                          ['class' => 'form-control input_cod_ingreso'])
                      ;?>
              <?= $form->field(
                      $model,
                      "[{$i}]cod_egreso",
                      ['options' => ['class' => 'field_cod_egreso']]
                      )
                      ->dropDownList(
                          ConsultaBalanceHidrico::$tipos_cod_egreso,
                          ['class' => 'form-control input_cod_egreso'])
                      ;?>    
            </div>
            <div class="col-2">
                  <a class="remove-item text-warning" href="#" data-bs-toggle="tooltip" data-bs-placement="right" data-bs-original-title="Quitar esta fila">
                     <?= $this->render('../site/svg_icon_remove.php'); ?>
                  </a>
            </div>
      </div>
      <div class="row">
            <div class="col-md-2">
              <?= $form->field($model, "[{$i}]hora_inicio")
                      ->widget(TimePicker::classname(), [
                                  'pluginOptions' => [
                                      'upArrowStyle' => 'bi bi-chevron-up',
                                      'downArrowStyle' => 'bi bi-chevron-down',
                                      'showMeridian' => false,
                                  ],
                                  'addon' => '<i class="bi bi-clock"></i>',
                              ]); ?>
              </div>
              <div class="col-md-2">
              <?= $form->field($model, "[{$i}]hora_fin")
                      ->widget(TimePicker::classname(), [
                                  'pluginOptions' => [
                                      'upArrowStyle' => 'bi bi-chevron-up',
                                      'downArrowStyle' => 'bi bi-chevron-down',
                                      'showMeridian' => false,
                                  ],
                                  'addon' => '<i class="bi bi-clock"></i>',
                              ]); ?>
              </div>
              <div class="col-md-2">
              <?= $form->field($model, "[{$i}]cantidad")->textInput() ?>
              </div>
      </div>
      <hr class="border border-info border-1 opacity-50">
    </div>
    <?php endforeach; ?>
</div>
<div class="row pe-2">
  <div class="col-xs-12">
      <button type="button" class="add-item mt-1 btn btn-info rounded-pill text-white">
          <i class="bi bi-plus-circle text-white"></i> Agregar Balance
      </button>
  </div>
</div>
<?php DynamicFormWidget::end(); ?>

  
<br/>
<div class="form-group">
<?php if($is_ajax): ?>
    <?php if ($consulta->urlAnterior) { ?>
        <?= Html::a(
              'Anterior', 
              $consulta->urlAnterior,
              ['class' => 
                'btn btn-primary atender rounded-pill float-start']) ?>
    <?php } ?>
    <?= Html::submitButton(
          $consulta->urlSiguiente == 'fin' ? 'Finalizar' : 'Guardar y Continuar',
          ['class' => 'btn btn-primary rounded-pill float-end']) ?>
    <?php
    $headerMenu = $consulta->getHeader();
    $header = "$('#modal-consulta-label').html('".$headerMenu."')";
    $this->registerJs($header);
    ?>
<?php else:?>
  <?= Html::submitButton('Guardar', ['class' => 'btn btn-success']) ?>
  <?= Html::a(
              'Cancelar',
              ['/consulta-balance-hidrico'],
              ['class' => 'btn btn-danger', 'role' => 'button']) ?>
    </div>
<?php endif;?>
<?php ActiveForm::end(); ?>

<?php /*
$headerMenu = $modelConsulta->getHeader();
$header = "$('#modal-consulta-label').html('".$headerMenu."')";
$this->registerJs($header);
*/ ?>

<?php
$script = <<<JS
var tipo_registro_ingreso = 'Ingreso';
function tipo_ingreso_changed(
    tipo_registro,
    cod_ingreso,
    cod_egreso,
    input_cod_ingreso,
    input_cod_egreso
) {
    if(tipo_registro.val() == tipo_registro_ingreso) {
            cod_ingreso.show();
            cod_egreso.hide();
            //input_cod_egreso.val("");
        }
        else {
            cod_ingreso.hide();
            //input_cod_ingreso.val("");
            cod_egreso.show();
        }
};
        
function inicializar_filas() {
    $(".item").each(function (index, element) {
      var tipo_registro = $(element).find(".field_tipo_registro");
      var cod_ingreso = $(element).find(".field_cod_ingreso");
      var cod_egreso = $(element).find(".field_cod_egreso");
      var input_cod_ingreso = $(element).find(".input_cod_ingreso");
      var input_cod_egreso = $(element).find(".input_cod_egreso");

      tipo_ingreso_changed(
          tipo_registro, 
          cod_ingreso,
          cod_egreso,
          input_cod_ingreso,
          input_cod_egreso
      );
      
      tipo_registro.off('change');
      tipo_registro.on('change', function() {
          tipo_ingreso_changed(
              tipo_registro, 
              cod_ingreso,
              cod_egreso,
              input_cod_ingreso,
              input_cod_egreso
          );
      });
    });
};

$( document ).ready(function() {
    inicializar_filas();
    
    $(".balances_dform_wrapper").on("afterInsert", function(e, item) {
        inicializar_filas();
    });
});
JS;

$this->registerJs($script);