<?php

use yii\helpers\Html;
use yii\helpers\Url;
use yii\web\JsExpression;
use yii\bootstrap5\ActiveForm;
use kartik\select2\Select2;
use kartik\date\DatePicker;
use kartik\time\TimePicker;
use yii\helpers\ArrayHelper;

use wbraganca\dynamicform\DynamicFormWidget;
use common\assets\SisseDynamicFormAsset;


SisseDynamicFormAsset::register($this);

/* @var $this yii\web\View */
/* @var $model common\models\ConsultaRegimen */
/* @var $form yii\widgets\ActiveForm */
?>

<?php 
$form = ActiveForm::begin(['id' => 'form_regimenes']);
if($is_ajax) {
  echo Html::hiddenInput('from_step_forms', 1);
} 

  DynamicFormWidget::begin([
      'widgetContainer' => 'regimenes_dform_wrapper', // required: only alphanumeric characters plus "_" [A-Za-z0-9_]
      'widgetBody' => '.container-items', // required: css class selector
      'widgetItem' => '.item', // required: css class
      'limit' => 10, // the maximum times, an element can be cloned (default 999)
      'min' => 0, // 0 or 1 (default 1)
      'insertButton' => '.add-item', // css class
      'deleteButton' => '.remove-item', // css class
      'model' => $regimenes[0],
      'formId' => 'form_regimenes',
      'formFields' => [
          'concept_id',
          'indicaciones'
      
      ],
  ]);
  ?>

<div class="container-items">
  <?php foreach ($regimenes as $i => $model) : ?>
    <div class="item">
      <div class="row">
        <div class="col">
          <?php
          $data = !$model->concept ? [] : [$model->concept_id => $model->concept->term];
          $concept_options = [
                  'minimumInputLength' => 4,
                  'width' => '100%',
                  'ajax' => [
                      'url' => Url::to(['snowstorm/practicas']),
                      'dataType' => 'json',
                      'data' => new JsExpression('function(params) { return {q:params.term}; }'),
                      'cache' => true
                  ]
              ];
          if($is_ajax) {
            $concept_options['dropdownParent'] = '#modal-consulta';
          }
          
          echo $form->field($model, "[{$i}]concept_id")->widget(Select2::classname(), [
              'data' => $data,
              'size' => Select2::LARGE,
              'theme' => 'default',
              'options' => [
                  'placeholder' => '- Escriba el concepto -', 
                  'class' => 'snomed_simple_select2'],
              'pluginOptions' => $concept_options,
          ])->label(false)
          ?>
          <?= Html::hiddenInput(
                  "CustomAttribute[$i][termino_procedimiento]",
                  $model->getConceptTerm(),
                  ['id' => "consultaregimen-$i-concept_id-termino", 
                    'class' => "termino"]
                  );
          ?>
        </div>
        <div class="col">
          <?= $form->field($model, "[{$i}]indicaciones")
                    ->textarea([
                        'rows' => 5, 
                        'cols' => 50,
                        'class'=> "speech-input", 
                        "lang" => 'es'])
                    ->label(false)
                    ;?>
        </div>
        <div class="col-2">
          <a class="remove-item text-warning" href="#" data-bs-toggle="tooltip" data-bs-placement="right" data-bs-original-title="Quitar esta fila">
            <?= $this->render('../site/svg_icon_remove.php'); ?>
          </a>
        </div>
      </div> <!-- row -->
      <hr class="border border-info border-1 opacity-50">
    </div> <!-- item -->
    <?php endforeach; ?>
</div>
<div class="row pe-2">
  <div class="col-xs-12">
      <button type="button" class="add-item mt-1 btn btn-info rounded-pill text-white">
          <i class="bi bi-plus-circle text-white"></i> Agregar Regimen
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
              ['/consulta-regimen'],
              ['class' => 'btn btn-danger', 'role' => 'button']) ?>
    </div>
<?php endif;?>
<?php ActiveForm::end(); ?>