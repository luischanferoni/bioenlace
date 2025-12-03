<?php 
use yii\helpers\Url;
use yii\web\JsExpression;
use yii\helpers\Html;

use kartik\select2\Select2;
use wbraganca\dynamicform\DynamicFormWidget;

use common\models\ConsultaMedicamentos;

use common\assets\SisseDynamicFormAsset;
SisseDynamicFormAsset::register($this);
?>

<?php
    DynamicFormWidget::begin([
        'widgetContainer' => 'dynamicform_wrapper_'.$id_consultas_diagnosticos, // required: only alphanumeric characters plus "_" [A-Za-z0-9_]
        'widgetBody' => '.container-items_medicamentos_'.$id_consultas_diagnosticos, // required: css class selector
        'widgetItem' => '.item_medicamentos_'.$id_consultas_diagnosticos, // required: css class
        'limit' => 10, // the maximum times, an element can be cloned (default 999)
        'min' => 0, // 0 or 1 (default 1)
        'insertButton' => '.add-item_medicamentos_'.$id_consultas_diagnosticos, // css class
        'deleteButton' => '.remove-item_medicamentos_'.$id_consultas_diagnosticos, // css class
        'model' => $modelosConsultaMedicamentos[0],
        'formId' => 'form-medicamentos',
        'formFields' => [
            'id_snomed_medicamento',
            'indicaciones',
            'estado'
        ],
    ]);
?>

    <div class="container-items_medicamentos_<?=$id_consultas_diagnosticos?>">
        <?php foreach ($modelosConsultaMedicamentos as $i => $model_m_c) : ?>
            <div class="item_medicamentos_<?=$id_consultas_diagnosticos?> row mb-3 pb-2 pt-3 ">
                <div class="col-sm-11 col-xs-12">
                    <div class="row">                        
                        <?= $form->errorSummary($model_m_c); ?>
                        <div class="col-sm-12" id="select-medicamento">

                            <?php if (!$model_m_c->isNewRecord) { ?>
                                <?= $form->field($model_m_c, "[$i][$id_consultas_diagnosticos]id", ['inputOptions' => ['class' => 'form-control id']])->hiddenInput()->label(false); ?>
                            <?php } ?>

                            <?= Html::hiddenInput(
                                "ConsultaMedicamentos[$i][$id_consultas_diagnosticos][id_consultas_diagnosticos]", $id_consultas_diagnosticos
                            );?>

                            <?php $data = !$model_m_c->id_snomed_medicamento ? [] : [$model_m_c->id_snomed_medicamento => $model_m_c->snomedMedicamento->term]; ?>
                            <?=
                            $form->field($model_m_c, "[$i][$id_consultas_diagnosticos]id_snomed_medicamento")->widget(Select2::classname(), [
                                'data' => $data,
                                'size' => Select2::LARGE,
                                'theme' => 'default',
                                'options' => ['placeholder' => '- Seleccione el Medicamento -', 'class' => 'snomed_simple_select2'],
                                'pluginOptions' => [
                                    'minimumInputLength' => 4,
                                    'dropdownParent' => '#select-'.$i.'--medicamento',
                                    'width' => '100%',
                                    'ajax' => [
                                        'url' => Url::to(['snowstorm/medicamentos']),
                                        'dataType' => 'json',
                                        'delay'=> 500,
                                        'data' => new JsExpression('function(params) { return {q:params.term}; }'),
                                        'cache' => true
                                    ]
                                ],
                            ])
                            ?>
                            <?= Html::hiddenInput(
                                "CustomAttribute[$i][$id_consultas_diagnosticos][termino_medicamento]",
                                !$model_m_c->snomedMedicamento ? '' : $model_m_c->snomedMedicamento->term,
                                ['id' => "consultamedicamentos-$i-$id_consultas_diagnosticos-id_snomed_medicamento-termino", 'class' => "termino"]
                            );
                            ?>
                        </div>
                        <div class="col-sm-12">
                            <div class="row">
                                <div class="col-sm-2 col-xs-2">
                                    <?= $form->field($model_m_c, "[$i][$id_consultas_diagnosticos]cantidad")->textInput(
                                                        [
                                                            'type' => 'number',
                                                            'min' => '0.25',
                                                            'step' => '0.25',
                                                            'data-bs-toggle' => 'tooltip',
                                                            'data-bs-placement' => 'right',
                                                            'data-bs-original-title' => 'Cantidad'
                                                        ]) ?>
                                </div>                            
                                <div class="col-sm-2 col-xs-2">
                                    <?= $form->field($model_m_c, "[$i][$id_consultas_diagnosticos]frecuencia")->textInput(
                                                            [
                                                            'type' => 'number',
                                                            'min' => '1',
                                                            ]) ?>
                                </div>
                                <div class="col-sm-4 pt-2 ps-0 mb-3">
                                    <label></label>
                                    <?= Html::radioList('frecuencia_tipo', 'MINUTO',                                
                                            ConsultaMedicamentos::FRECUENCIAS,
                                            [
                                                'item' => function($index, $label, $name, $checked, $value) use ($i, $id_consultas_diagnosticos, $model_m_c) {
                                                    $check = $model_m_c->frecuencia_tipo === $value ? 'checked' : '';
                                                    $return = '<input class="btn-check" type="radio" name="ConsultaMedicamentos['.$i.']['.$id_consultas_diagnosticos.'][' . $name . ']" value="' . $value . '" id="'.$name.'-'.$i.'-'.$id_consultas_diagnosticos.'-'.$index.'" '.$check.'>';
                                                    $return .= '<label class="btn btn-outline-secondary" for="'.$name.'-'.$i.'-'.$id_consultas_diagnosticos.'-'.$index.'">' . $value . '</label>';                            

                                                    return $return;
                                                }
                                            ]); 
                                    ?>
                                </div>
                            </div>
                        </div>

                        <div class="col-sm-12">
                            <div class="row">
                                <div class="col-sm-2 col-xs-2"> 
                                    <?= $form->field($model_m_c, "[$i][$id_consultas_diagnosticos]durante")->textInput(
                                                                [
                                                                'type' => 'number',
                                                                'min' => '1',
                                                                ]) ?>
                                </div>
                                <div class="col-sm-5 pt-2 ps-0 mb-3">
                                    <label></label>
                                    <?= Html::radioList('durante_tipo', 'MINUTO',                                
                                            ConsultaMedicamentos::DURANTES, 
                                            [
                                                'item' => function($index, $label, $name, $checked, $value) use ($i, $id_consultas_diagnosticos, $model_m_c) {
                                                    $check = $model_m_c->durante_tipo === $value ? 'checked' : '';
                                                    $return = '<input class="btn-check" type="radio" name="ConsultaMedicamentos['.$i.']['.$id_consultas_diagnosticos.'][' . $name . ']" value="' . $value . '" id="'.$name.'-'.$i.'-'.$id_consultas_diagnosticos.'-'.$index.'" '.$check.'>';
                                                    $return .= '<label class="btn btn-outline-secondary" for="'.$name.'-'.$i.'-'.$id_consultas_diagnosticos.'-'.$index.'">' . $value . '</label>';

                                                    return $return;
                                                }
                                            ]); 
                                    ?>
                                </div>
                            </div>
                        </div>

                        <div class="<?= $model_m_c->isNewRecord ? 'col-sm-12' : 'col-sm-10' ?>">
                            <?= $form->field($model_m_c, "[$i][$id_consultas_diagnosticos]indicaciones")->textArea(['placeholder' => 'Más Indicaciones'])->label(false) ?>
                        </div>
                        <?php if (!$model_m_c->isNewRecord) { ?>
                            <div class="col-sm-2">
                                <?= $form->field($model_m_c, "[$i][$id_consultas_diagnosticos]estado")->dropDownList(ConsultaMedicamentos::ESTADOS, ['prompt' => '- Estado -'])->label(false) ?>
                            </div>
                        <?php } ?>
                    </div>
                </div>

                <div class="col-sm-1 col-xs-12 text-center">
                    <a class="float-xl-end remove-item_medicamentos_<?=$id_consultas_diagnosticos?> text-warning" href="#" data-bs-toggle="tooltip" data-bs-placement="right" data-bs-original-title="Quitar esta fila">
                        <svg class="icon-32" width="32" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M14.3955 9.59497L9.60352 14.387" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
                            <path d="M14.3971 14.3898L9.60107 9.59277" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
                            <path fill-rule="evenodd" clip-rule="evenodd" d="M16.3345 2.75024H7.66549C4.64449 2.75024 2.75049 4.88924 2.75049 7.91624V16.0842C2.75049 19.1112 4.63549 21.2502 7.66549 21.2502H16.3335C19.3645 21.2502 21.2505 19.1112 21.2505 16.0842V7.91624C21.2505 4.88924 19.3645 2.75024 16.3345 2.75024Z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
                        </svg>
                    </a>
                </div>
                <hr class="border border-light border-1 opacity-50">
            </div>

        <?php endforeach; ?>
    </div>
    <div class="row pe-2">
        <div class="col-xs-12">
            <button type="button" class="add-item_medicamentos_<?=$id_consultas_diagnosticos?> mt-2 btn btn-info rounded-pill float-xl-end text-white">
                <i class="bi bi-plus-circle text-white"></i> Agregar Medicamento
            </button>
        </div>
    </div>

    <?php DynamicFormWidget::end(); ?>