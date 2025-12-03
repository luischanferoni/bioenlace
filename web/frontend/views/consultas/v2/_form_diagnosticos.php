<?php

use yii\helpers\Url;
use yii\helpers\Html;
use yii\web\JsExpression;
use yii\bootstrap5\ActiveForm;

use kartik\select2\Select2;
use wbraganca\dynamicform\DynamicFormWidget;

use common\assets\SisseDynamicFormAsset;
use common\models\DiagnosticoConsulta;

SisseDynamicFormAsset::register($this);

$show_prev_diag = true;
$has_diag_prev = count($diagnosticos_previos) > 0;
?>

<?php $form = ActiveForm::begin(['id' => 'form-diagnosticos']); ?>
<?php echo $form->errorSummary($form_model);?>
<?php if($show_prev_diag): ?>
<div class="p-6 pb-0">
  <div class="border-bottom mb-1">
    <h4 class="text-xl-start fs-5 text-black dark:text-white mb-0">
    Diagnósticos Previos
    </h4>
    <p class="text-info fs-6">(Seguimiento)</p>
  </div>
  <div>
    <?php if(!$has_diag_prev) : ?>
    Sin diagnósticos previos.
    <?php else: ?>
    <div class="row">
        <div class="col-2">
            &nbsp;
        </div>
        <div class="col-2">
        Diagnóstico
        </div>
        <div class="col-1">
        Crónico
        </div>
        <div class="col-2 pe-0">
        Estado Actual
        </div>
        <div class="col-2">
        Nuevo E. Clínico
        </div>
        <div class="col-2">
        Nuevo E. Verificación
        </div>
    </div>
    <?php foreach ($diagnosticos_previos as $i => $dprev) : ?>
    <div class="row">
        <div class="col-2">
        <?= Html::activeHiddenInput($dprev, "[{$i}]id");?>
        <?= $form->field($dprev, "[$i]resolve",
                ['options' => ['class' => "pt-2"]])
                ->checkbox(['value' => 'Y', 'uncheck' => 'N']) ?>
        </div>
        <div class="col-2">
        <?= $form->field($dprev,"[$i]diagnostico")
            ->textInput(['readonly'=> true])
            ->label(false)?>
        </div>
        <div class="col-1">
        <?= $form->field($dprev, "[$i]cronico")
            ->textInput(['readonly'=> true])
            ->label(false)?>
        </div>
        <div class="col-2 pe-0">
        <?= $form->field($dprev, "[$i]current_state")
            ->textInput(['readonly'=> true])
            ->label(false)?>
        </div>
        <?php
        $status_ops = ['prompt' => '- Seleccione -'];
        $readonly_status = $dprev->cronico == 'SI';
        if($readonly_status) {
            $status_ops['disabled'] = 'disabled';
        }
        ?>
        <div class="col-2">
        <?= $form->field($dprev, "[$i]new_cclinical_status")
                ->dropDownList(
                    $clinical_statuses_for_prev,
                    $status_ops)
                ->label(false)?>
        </div>
        <div class="col-2">
        <?= $form->field($dprev, "[$i]new_cverification_status")
            ->dropDownList(
                    $verification_statuses_for_prev,
                    $status_ops)
            ->label(false)?>
        </div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>
<?php endif; ?>

<div class="p-6 pb-0 mt-5">
  <div class="border-bottom mb-1">
    <h4 class="text-xl-start fs-5 text-black dark:text-white mb-0">
    Diagnósticos Nuevos
    </h4>
    <p class="text-info fs-6">(Detectados en la atención en curso)</p>
  </div>
  <div>
    <?php
    DynamicFormWidget::begin([
        'widgetContainer' => 'dynform_diagnosticos', // required: only alphanumeric characters plus "_" [A-Za-z0-9_]
        'widgetBody' => '.container-items_diagnostico', // required: css class selector
        'widgetItem' => '.item_diagnostico', // required: css class
        #'limit' => 4, // the maximum times, an element can be cloned (default 999)
        'min' => $min_diag, // 0 or 1 (default 1)
        'insertButton' => '.add-item_diagnostico', // css class
        'deleteButton' => '.remove-item_diagnostico', // css class
        'model' => $modelosConsultaDiagnosticos[0],
        'formId' => 'form-diagnosticos',
        'formFields' => [
            'codigo',
            //'termino_hallazgo',
            'cronico',
            'condition_clinical_status',
            'condition_verification_status'
        ],
    ]);
    ?>

    <div class="container-items_diagnostico pe-5">
      <?php foreach ($modelosConsultaDiagnosticos as $i => $modelDiagnostico) : ?>

        <div class="item_diagnostico row mb-2 d-flex align-items-center form-group pb-3 pt-2">
          <?php //echo $form->errorSummary($modelDiagnostico);?>
          <div class="row">
            <div class="col" id= 'select-<?= $i ?>--diagnostico'>
              <?php
              if (!$modelDiagnostico->isNewRecord) {
                 echo $form->field(
                         $modelDiagnostico, 
                         "[$i]id", 
                         [
                            'options' => ["class" => ''],
                            'inputOptions' => ['class' => '']
                         ])
                      ->hiddenInput()->label(false);
              }
              $data = !$modelDiagnostico->codigoSnomed ? [] : [$modelDiagnostico->codigo => $modelDiagnostico->codigoSnomed->term];

              echo $form->field(
                      $modelDiagnostico, 
                      "[{$i}]codigo")->widget(Select2::classname(),
                      [
                        'data' => $data,
                        'size' => Select2::LARGE,
                        'theme' => 'default',
                        'options' => [
                            'placeholder' => '- Escriba el Diagnóstico -',
                            'class' => 'snomed_simple_select2',
                            'data-diagcodigo'=>'true'],
                        'pluginOptions' => [
                            'minimumInputLength' => 4,
                            'dropdownParent' => '#select-'.$i.'--diagnostico',
                            'width' => '100%',
                            'ajax' => [
                                'url' => Url::to(['snowstorm/diagnosticos']),
                                'dataType' => 'json',
                                'delay'=> 500,
                                'data' => new JsExpression('function(params) { return {q:params.term}; }'),
                                'cache' => true
                            ],
                        ],
                      ])
                    ->label("Diagnóstico");
              // El attributo id es el mismo que el select2 + -termino 
              echo Html::hiddenInput(
                      "CustomAttribute[$i][termino_hallazgo]",
                      !$modelDiagnostico->codigoSnomed ? '' : $modelDiagnostico->codigoSnomed->term,
                      ['id' => "diagnosticoconsulta-$i-codigo-termino", 'class' => "termino"]
                      );
              ?>
            </div>
            
            <div class="col-3 col-sm-3">
              <?= $form->field(
                      $modelDiagnostico,
                      "[{$i}]condition_clinical_status",
                      [
                          'options' => ['class' => 'mb-0']
                        ])
                ->dropDownList(
                    $clinical_statuses_for_new,
                    ['prompt' => '- Seleccione -',
                     'data-ccstatus'=>'true',
                        ])
                ->label("Estado Clínico") ?>
            </div>
            <div class="col-3 col-sm-2 pe-0">
              <?= $form->field(
                      $modelDiagnostico, 
                      "[{$i}]condition_verification_status", 
                      [
                        'options' => ['class' => 'mb-0']
                      ])
                    ->dropDownList(
                        $verification_statuses_for_new,
                        ['prompt' => '- Seleccione -',
                         'data-cvstatus'=>'true',])
                    ->label('Verificación') ?>
            </div>

            <div class="col-2 text-center">
              <a class="float-xl-end remove-item_diagnostico text-warning" href="#" data-bs-toggle="tooltip" data-bs-placement="right" data-bs-original-title="Quitar esta fila">
              <?= $this->render('../../site/svg_icon_remove.php'); ?>
              </a>
            </div>
          </div><!-- end row -->
          
          <div class="row">
            <div class="col-3 col-sm-2">
              <?= $form->field(
                      $modelDiagnostico, 
                      "[{$i}]cronico", 
                      [
                        'labelOptions' => ['class' => 'pt-1 ps-1'],
                        'options' => ['class' => '']
                      ])
                  ->checkbox([
                      'value' => 'SI', 
                      'uncheck' => null]) ?>
            </div>            
          </div> <!-- end row -->
          <hr class="border border-1 border-gray mt-2">
        </div>
      <?php endforeach; ?>
    </div>
      <div class="row pe-2">
        <div class="col-xs-12">
            <button type="button" class="add-item_diagnostico mt-1 btn btn-info rounded-pill float-xl-end text-white">
              <i class="bi bi-plus-circle text-white"></i> Agregar Diagnóstico
            </button>
        </div>
      </div>
    <?php DynamicFormWidget::end(); ?>

  </div> <!-- end card body -->
</div> <!-- end card -->
<!------ Formulario Dinámico ------->

<hr class="border border-info border-1 opacity-50">
<?php if ($modelConsulta->urlAnterior): ?>
    <?= Html::a(
            'Anterior', 
            $modelConsulta->urlAnterior, 
            ['class' => 'btn btn-primary atender rounded-pill float-start'])
        ?>
<?php endif; ?>
<?= Html::submitButton(
        $modelConsulta->urlSiguiente == 'fin' ? 'Finalizar' : 'Guardar y Continuar',
        ['class' => 'btn btn-primary rounded-pill float-end'])
        ?>

<?php ActiveForm::end(); ?>

<?php
$headerMenu = $modelConsulta->getHeader();
$header = "$('#modal-consulta-label').html('".$headerMenu."')";
$this->registerJs($header);

$js = <<<JS
jQuery(".dynform_diagnosticos").on("afterInsert", function(e, item) {
    $(item).find('[data-ccstatus=true]').val('ACTIVE');
    $(item).find('[data-cvstatus=true]').val('PROVISIONAL');
    // $(item).find('[data-diagcodigo=true]').focus();
});
JS;
$this->registerJs($js);

