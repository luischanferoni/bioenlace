<?php

use yii\helpers\ArrayHelper;
use yii\helpers\Url;
use yii\helpers\Html;
use yii\web\JsExpression;
use yii\bootstrap5\ActiveForm;

use common\models\Cie10;
use common\models\Consulta;
use common\models\ConsultaMedicamentos;

?>

<?php $form = ActiveForm::begin(['id' => 'form-medicamentos', 'enableClientValidation' => false]); ?>

    <div class="accordion accordion-flush" id="diagnosticos">
        <?php 
        $i = 0;
        foreach ($modelConsulta->diagnosticoConsultas as $iDiagnostico => $diagnostico) : 
        ?>
            <div class="accordion-item">
                <h2 class="accordion-header">
                    <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#flush-<?=$modelConsulta->id_consulta.'-'.$diagnostico->id?>" aria-expanded="true" aria-controls="flush-collapseOne">
                        <?=$diagnostico->codigoSnomed->term?>
                    </button>
                </h2>
                <div id="flush-<?=$modelConsulta->id_consulta.'-'.$diagnostico->id?>" class="accordion-collapse collapse show" data-bs-parent="#accordionFlushExample">
                    <div class="accordion-body">
                        <?= $this->render("_form_medicamento", [
                                'form' => $form,
                                'iDiagnostico' => $iDiagnostico,
                                'index' => $i,
                                'id_consultas_diagnosticos' => $diagnostico->id,
                                'modelosConsultaMedicamentos' => isset($modelosConsultaMedicamentos[$diagnostico->id]) ? $modelosConsultaMedicamentos[$diagnostico->id] : [new ConsultaMedicamentos]
                                ]) ?>
                    </div>
                </div>
            </div>
        <?php 
                $i++;
                endforeach; ?>
    </div>

    <hr class="border border-info border-1 opacity-50">
    <?php if ($modelConsulta->urlAnterior) { ?>
        <?= Html::a('Anterior', $modelConsulta->urlAnterior, ['class' => 'btn btn-primary atender rounded-pill float-start']) ?>
    <?php } ?>
    <?= Html::submitButton($modelConsulta->urlSiguiente == 'fin' ? 'Finalizar' : 'Guardar y Continuar', ['class' => 'btn btn-primary rounded-pill float-end']) ?>

<?php ActiveForm::end(); ?>

<?php
$headerMenu = $modelConsulta->getHeader();
$header = "$('#modal-consulta-label').html('".$headerMenu."')";
$this->registerJs($header);
?>