<?php

use yii\helpers\Html;
use yii\bootstrap5\ActiveForm;
use kartik\date\DatePicker;
use wbraganca\dynamicform\DynamicFormWidget;

use yii\helpers\ArrayHelper; 
use yii\helpers\Url;

/* @var $this yii\web\View */
/* @var $model common\models\Consulta */
/* @var $form yii\widgets\ActiveForm */

$this->registerJsFile(
    '@web/js/speech-input.js',
    ['depends' => [] ]
);

$this->registerCssFile(
    '@web/css/speech-input.css',
    ['depends' => [] ]
);
?>
<div class="form-card card mb-3 border rounded border-0">

    <div class="card-body">
        <div class="alert alert-left alert-success alert-dismissible fade show mb-3" role="alert">
            <span><i class="fas fa-thumbs-up"></i></span>
            <span> Al activar el microfono se transcribirá el audio!</span>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php $form = ActiveForm::begin(['id' => 'form-evolucion', 'enableClientValidation' => false]); ?>

        <div class="row" style="padding: 1%">    
            <div class="col-12">    

                <?=
                    $form->field($modeloEvolucion, 'evolucion')
                    ->textarea(['rows' => 5, 'class'=> "speech-input", "lang" => 'es'])
                    ->label(false);
                ?>

            </div>                  
        </div>

        <div class="row">
            <div class="col-xs-12">
                <?= Html::submitButton($modelConsulta->urlSiguiente == 'fin' ? 'Finalizar' : 'Siguiente', ['class' => 'btn btn-primary rounded-pill float-end']) ?>
            </div>
        </div>
        <?php ActiveForm::end(); ?>
    </div>
</div>

<?php
$headerMenu = $modelConsulta->getHeader();
$header = "$('#modal-consulta-label').html('".$headerMenu."')";
$this->registerJs($header);
?>