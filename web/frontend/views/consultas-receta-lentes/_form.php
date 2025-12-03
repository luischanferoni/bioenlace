<?php

use yii\helpers\Html;
use yii\bootstrap5\ActiveForm;

/* @var $this yii\web\View */
/* @var $model common\models\ConsultasRecetaLentes */
/* @var $form yii\widgets\ActiveForm */
?>

<div class="consultas-receta-lentes-form">

    <?php $form = ActiveForm::begin(); ?>
    <?php if ($estcomp): ?>
        <?php foreach ($estcomp as $e): ?>
            <?php if ($e->codigo == "252886007") { ?>

                <h5 class="mb-2 text-center">Resultado de la Refracción</h5>
                <div class="row justify-content-center mb-3">
                    <div class="w-25">
                        <table class="table table-sm">
                            <tr class="table-active">
                                <th scope="col">Ojo</th>
                                <th scope="col">Resultado</th>
                            </tr>
                            <tr>
                                <td><?php echo $e->ojo ?></td>
                                <td><?php echo $e->informe ?></td>
                            </tr>
                        </table>
                    </div>
                </div>
            <?php } ?>
        <?php endforeach ?>
    <?php endif ?>
    <?php if ($disabled): ?>
        <div class="col-sm-2">
            <?= $form->field($model, 'id_consulta')->hiddenInput()->label(false) ?>
        </div>

        <div class="row justify-content-center mb-3">
            <div class="col-sm-2">
                <h5>De lejos</h5>
            </div>
            <div class="col-sm-2"></div>
            <div class="col-sm-2"></div>
        </div>

        <div class="row justify-content-center">
            <div class="col-sm-2">
                <h6>Esfera Ojo Derecho</h6>
                <?= $form->field($model, 'od_esfera')->textInput(['maxlength' => true])->label(false) ?>
            </div>
            <div class="col-sm-2">
                <h6>Cilindro Ojo Derecho</h6>
                <?= $form->field($model, 'od_cilindro')->textInput(['maxlength' => true])->label(false) ?>
            </div>
            <div class="col-sm-2">
                <h6>Eje Ojo Derecho</h6>
                <?= $form->field($model, 'od_eje')->textInput(['maxlength' => true])->label(false) ?>
            </div>
        </div>
        <div class="row justify-content-center">
            <div class="col-sm-2">
                <h6>Esfera Ojo Izquierdo</h6>
                <?= $form->field($model, 'oi_esfera')->textInput(['maxlength' => true])->label(false)->label(false) ?>
            </div>
            <div class="col-sm-2">
                <h6>Cilindro Ojo Izquierdo</h6>
                <?= $form->field($model, 'oi_cilindro')->textInput(['maxlength' => true])->label(false) ?>
            </div>
            <div class="col-sm-2">
                <h6>Eje Ojo Izquierdo</h6>
                <?= $form->field($model, 'oi_eje')->textInput(['maxlength' => true])->label(false) ?>
            </div>

        </div>


        <div class="row justify-content-center mt-3 mb-3">
            <div class="col-sm-2">
                <h5>De cerca</h5>
            </div>
            <div class="col-sm-2"></div>
            <div class="col-sm-2"></div>
        </div>

        <div class="row justify-content-center">
            <div class="col-sm-2">
                <h6>ADD ojo derecho</h6>
                <?= $form->field($model, 'od_add')->textInput(['maxlength' => true])->label(false) ?>
                
            </div>
            <div class="col-sm-2">
                <h6>ADD ojo Izquierdo</h6>
                <?= $form->field($model, 'oi_add')->textInput(['maxlength' => true])->label(false) ?>
            </div>
            <div class="col-sm-2"></div>
                
        </div>



    <?php else: ?>
        <div class="alert alert-warning" role="alert">
            Para poder recetar lentes debe cargar antes, una evaluación de Refracción!...
        </div>
    <?php endif ?>
    <hr class="border border-info border-1 opacity-50">
    <?php if ($form_steps): ?>
        <?php if ($modelConsulta->urlAnterior) { ?>
            <?= Html::a(
                'Anterior',
                $modelConsulta->urlAnterior,
                ['class' =>
                'btn btn-primary atender rounded-pill float-start']
            ) ?>
        <?php } ?>
        <?= Html::submitButton(
            $modelConsulta->urlSiguiente == 'fin' ? 'Finalizar' : 'Guardar y Continuar',
            ['class' => 'btn btn-primary rounded-pill float-end']
        ) ?>
        <?php
        $headerMenu = $modelConsulta->getHeader();
        $header = "$('#modal-consulta-label').html('" . $headerMenu . "')";
        $this->registerJs($header);
        ?>
    <?php else: ?>
        <div class="form-group">
            <?= Html::submitButton('Guardar', ['class' => 'btn btn-success']) ?>
            <?= Html::a(
                'Cancelar',
                ['/consulta-practicas-oftalmologia'],
                ['class' => 'btn btn-danger', 'role' => 'button']
            ) ?>
        </div>
    <?php endif; ?>

    <?php ActiveForm::end(); ?>

</div>