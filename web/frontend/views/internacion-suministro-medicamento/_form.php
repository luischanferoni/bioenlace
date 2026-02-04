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
                <div class="card-body">

                    <?php $form = ActiveForm::begin(); ?>
                    <?= $form->errorSummary($model) ?>
                    <?php echo $error ? '<ul><li> '.$error.'</li></ul>' : ''?>
                    <?= $form->field($model, 'id_internacion')->hiddenInput()->label(FALSE) ?>
                    <table class="table">
                        <thead>
                            <tr>
                                <th scope="col"></th>
                                <th scope="col">Nombre</th>
                                <th scope="col">Indicacion</th>
                            </tr>
                        </thead>
                        <?php foreach($medicamentosInternacion as $medicamento): ?>
                            <tr>
                                <td><?php echo Html::checkbox('med[]', false, ['value' => $medicamento->id]) ?></td>
                                <td><?php echo $medicamento->getSnomedMedicamento()->one()->term?></td>
                                <td><?php echo $medicamento->indicaciones?></td>
                            </tr>
                        <?php endforeach ?>
                    </table>

                    <div class="row">
                        <div class="col">
                            <?= $form->field($model, 'fecha')->widget(DatePicker::className(), [
                                'type' => DatePicker::TYPE_COMPONENT_APPEND,
                                'pickerIcon' => '<i class="bi bi-calendar2-week"></i>',
                                'removeIcon' => '<i class="bi bi-trash"></i>',
                                'pluginOptions' => [
                                    'autoclose' => true
                                ]
                            ]) ?>
                        </div>

                        <div class="col">
                            <?= $form->field($model, 'hora')->widget(TimePicker::classname(), [
                                'pluginOptions' => ['upArrowStyle' => 'bi bi-chevron-up', 'downArrowStyle' => 'bi bi-chevron-down'],
                                'addon' => '<i class="bi bi-clock"></i>',
                            ]); ?>
                        </div>

                    </div>

                    <?= $form->field($model, 'observacion')->textInput() ?>

                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <?= Html::submitButton($model->isNewRecord ? 'Guardar' : 'Guardar cambios', ['class' => $model->isNewRecord ? 'btn btn-success rounded-pill' : 'btn btn-primary rounded-pill']) ?>
                        <?= Html::a('Cancelar', ['internacion/view', 'id' => $model->id_internacion], ['class' => 'btn btn-danger rounded-pill']) ?>
                    </div>

                </div>
            </div>
        </div>
        <div class="col-sm-2"></div>
    </div>

    <?php ActiveForm::end(); ?>

</div>