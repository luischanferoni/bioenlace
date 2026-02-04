<?php

use yii\helpers\Html;
use yii\widgets\ActiveForm;
use common\models\SegNivelInternacionMedicamento;
use common\models\RrhhEfector;
use kartik\select2\Select2;
use kartik\time\TimePicker;
use kartik\date\DatePicker;
use yii\helpers\ArrayHelper;
use common\widgets\Alert;

/* @var $this yii\web\View */
/* @var $model common\models\InternacionSuministroMedicamento */
/* @var $form yii\widgets\ActiveForm */
?>
<?= Alert::widget() ?>

<div class="internacion-suministro-medicamento-form">
    <div class="row">
        <div class="col-sm-2"></div>
        <div class="col-sm">
            <div class="card">
                <div class="card-header">
                    <div class="header-title">
                        <h4 class="card-title">Suministro de Medicamentos</h4>
                    </div>
                </div>
                <div class="card-body">


                    <?php $form = ActiveForm::begin(); ?>

                    <?php foreach ($modelosConsultaSuministros as $i => $modelSuministro) { ?>

                        <?= $form->field($modelSuministro, 'id_consulta')->hiddenInput()->label(FALSE) ?>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th scope="col"></th>
                                        <th scope="col">Nombre</th>
                                        <th scope="col">Indicacion</th>
                                    </tr>
                                </thead>
                                <?php foreach ($medicamentosInternacion as $medicamento) : ?>
                                    <tr>
                                        <td><?= $form->field($modelSuministro, "[{$i}]id_internacion_medicamento")->checkbox(['value' => $medicamento->id])->label(false) ?></td>
                                        <td><?php echo $medicamento->getSnomedMedicamento()->one()->term ?></td>
                                        <td><?php echo $medicamento->indicaciones ?></td>
                                    </tr>
                                <?php endforeach ?>
                            </table>
                        </div>

                             <?php } ?>

                        <div class="row">
                            <div class="col">
                                <?= $form->field($modelSuministro, 'fecha')->widget(DatePicker::className(), [
                                    'type' => DatePicker::TYPE_COMPONENT_APPEND,
                                    'pickerIcon' => '<i class="bi bi-calendar2-week"></i>',
                                    'removeIcon' => '<i class="bi bi-trash"></i>',
                                    'pluginOptions' => [
                                        'autoclose' => true
                                    ]
                                ]) ?>
                            </div>

                            <div class="col">
                                <?= $form->field($modelSuministro, 'hora')->widget(TimePicker::classname(), [
                                    'pluginOptions' => ['upArrowStyle' => 'bi bi-chevron-up', 'downArrowStyle' => 'bi bi-chevron-down'],
                                    'addon' => '<i class="bi bi-clock"></i>',
                                ]); ?>
                            </div>

                        </div>

                        <?= $form->field($modelSuministro, 'observacion')->textInput() ?>

               

                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <?= Html::submitButton($modelConsulta->urlSiguiente == 'fin' ? 'Finalizar' : 'Siguiente', ['class' => 'btn btn-primary rounded-pill float-end']) ?>
                    </div>

                </div>
            </div>
        </div>
        <div class="col-sm-2"></div>
    </div>

    <?php ActiveForm::end(); ?>

</div>