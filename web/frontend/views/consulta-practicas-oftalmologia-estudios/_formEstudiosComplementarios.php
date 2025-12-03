<?php

use yii\helpers\Html;
use yii\helpers\Url;
use yii\web\JsExpression;
use yii\bootstrap5\ActiveForm;
use wbraganca\dynamicform\DynamicFormWidget;
use kartik\select2\Select2;

use common\assets\SisseDynamicFormAsset;

SisseDynamicFormAsset::register($this);

/* @var $this yii\web\View */
/* @var $model common\models\ConsultaPracticasOftalmologia */
/* @var $form yii\widgets\ActiveForm */
?>
<style>
    select[readonly].select2-hidden-accessible + .select2-container {
        pointer-events: none;
        touch-action: none;
    }

    select[readonly].select2-hidden-accessible + .select2-container .select2-selection {
        background: #eee;
        box-shadow: none;
    }

    select[readonly].select2-hidden-accessible + .select2-container .select2-selection__arrow, select[readonly].select2-hidden-accessible + .select2-container .select2-selection__clear {
        display: none;
    }

    svg {
         fill: none;
    }
</style>
<div class="consulta-practicas-oftalmologia-form">

    <?php $form = ActiveForm::begin(['id' => 'form_oftalmologia']); ?>

    <?php
    $min = ($oftalmologias[0]->isNewRecord) ? 0 : count($oftalmologias);
    DynamicFormWidget::begin([
        'widgetContainer' => 'ofta_dform_wrapper', // required: only alphanumeric characters plus "_" [A-Za-z0-9_]
        'widgetBody' => '.container-items', // required: css class selector
        'widgetItem' => '.item',
        'limit' => 10, // the maximum times, an element can be cloned (default 999)
        'min' => 0, // 0 or 1 (default 1)
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
    <div class="container-items ps-5 pe-5">

     

        <?php foreach ($oftalmologias as $i => $model) { ?>
        <div class="item">
            <div class="row">
                <?php  $data = !$model->codigoSnomed ? [] : [$model->codigo => $model->codigoSnomed->term]; ?>
                <?php
                $span = '';
                foreach ($arrayC as $ind => $practica):
                    $span = $span.'<span type=\"button\" class=\"form-control fixed_values\" value=\"'.$ind.','.$practica.'\">'.$practica.'</span>';
                endforeach;
                ?>
                <?php if($model->id_consultas_derivaciones):?>
                    <div class="col-sm-1 rechazado" id="rechazado">
                        <h6>Rechazar</h6>
                        <input type="checkbox" id="<?php echo $i?>_rechazado" name="<?php echo $i?>_rechazado">
                        <?=
                        $form->field($model, "[$i]id_consultas_derivaciones")->hiddenInput()->label(false);
                        ?>
                    </div>
                <?php endif?>
                <div class="col-sm-4">
                    <h6>Práctica</h6>
                    <?php
                    if ($model->codigo_deshabilitado) {
                        $options = ['placeholder' => '- Seleccione la Práctica -', 'class' => 'snomed_simple_select2', 'data-practica' => 'true', 'readonly' => 'readonly'];
                    } else {
                        $options = ['placeholder' => '- Seleccione la Práctica -', 'class' => 'snomed_simple_select2', 'data-practica' => 'true'];
                    }
                    ?>
                    <?=
                    $form->field($model, "[{$i}]codigo")->widget(Select2::classname(), [
                        'data' => $data,                        
                        'theme' => 'default',
                        'language' => 'es',
                        'options' => $options,
                        'pluginOptions' => [
                            'minimumInputLength' => 4,
                            'dropdownParent' => "#modal-consulta",
                            'ajax' => [
                                'url' => Url::to(['snowstorm/practicas']),
                                'dataType' => 'json',
                                'delay'=> 500,
                                'data' => new JsExpression('function(params) { return {q:params.term}; }'),
                                'cache' => true
                            ],
                            'width' => '80%',
                        ],
                    ])->label(false);

                    ###botones para practicas fijas#######
                    foreach ($arrayC as $ind => $practica):?>
                       <h4 type="button" class="badge bg-info d-lg-inline-block  fixed_values" value="<?php echo $ind.','.$practica?>"><?php echo $practica?></h4>
                    <?php endforeach?>

                    <?= Html::hiddenInput(
                        "CustomAttribute[$i][termino_procedimiento]",
                        !$model->codigoSnomed ? '' : $model->codigoSnomed->term,
                        ['id' => "consultapracticasoftalmologiaestudios-$i-codigo-termino", 'class' => "termino"]
                    );
                    ?>
                </div>

                <div class="col-sm-2">
                    <h6>Ojo</h6>
                    <?= $form->field($model, "[{$i}]ojo")->dropDownList($arrayO, ['prompt' => ''])->label(false) ?>
                </div>

                <div class="col-sm-2" id="resultado">
                    <h6>Resultado</h6>
                    <?= $form->field($model, "[{$i}]resultado")->textInput(['type' => 'string'])->label(false) ?> <h6> <input type="checkbox" id="<?php echo $i ?>no-evaluar" name="no-evaluar">No se puede evaluar</h6>
                </div>
                <div class="col-sm-2" id="informe">
                    <h6>Informe</h6>
                    <?= $form->field($model, "[{$i}]informe")->textarea(['rows' => 6])->label(false) ?>
                </div>
                <div class="col-sm-1 remove" hidden="hidden">
                    <a class="remove-item text-warning"
                       href="#" data-bs-toggle="tooltip"
                       data-bs-placement="right"
                       data-bs-original-title="Quitar esta fila">
                        <?= $this->render('../site/svg_icon_remove.php'); ?>
                    </a>
                </div>
                <hr class="border border-light border-1 opacity-50">
            </div>
        </div>
        <?php } ?>
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

</div>

<?php
$headerMenu = $modelConsulta->getHeader();
$header = "$('#modal-consulta-label').html('".$headerMenu."')";
$script = <<<JS

$(".js-programmatic-set-val").on("click", function () {
    $(".snomed_simple_select2").val("CA").trigger("change");
});
function codigo_changed(
    fixed_value,
    snomed_simple_select2,
    span_single
) {
            var split = 0;
            split = $(fixed_value).attr('value').split(',');
            console.log(split[0]);
            console.log(split[1]);
            //$(span_single).find('select2-selection__rendered').remove();
            //span_single.append('<span class="select2-selection__rendered"  role="textbox" aria-readonly="true" title="'+split[1]+'">'+split[1]+'</span>');
            snomed_simple_select2.append(':selected').empty();
            snomed_simple_select2.append('<option value="'+split[0]+'" selected="" data-select2-id="select2-data-3-'+split[0]+'">'+split[1]+'</option>');
}
        
function inicializar_filas() {
   
    $(".item").each(function (index, element) {
      var fixed_values = $(element).find(".fixed_values");
      var snomed_simple_select2 = $(element).find(".snomed_simple_select2");
      var span_single = $(element).find(".select2-selection--single");
        
        $(fixed_values).each(function (index, element2) {
          $(element2).on('click', function() {
              codigo_changed(
                  element2, 
                  snomed_simple_select2,
                  span_single,
              );
          });
      });
    });
}
$( document ).ready(function() {
    inicializar_filas();
    $(".ofta_dform_wrapper").on("afterInsert", function(e, item) {
        inicializar_filas();
    });
});

$(".ofta_dform_wrapper").on("afterInsert", function(e, item) { 
    $(item).find('[data-practica=true]').removeAttr('readonly');
    $(item).find('.rechazado').remove();
    $(item).find('.remove').removeAttr('hidden');
});

JS;

$this->registerJs($script);
?>
