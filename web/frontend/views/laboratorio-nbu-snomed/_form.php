<?php

use yii\helpers\Html;
use yii\widgets\ActiveForm;
use kartik\select2\Select2;
use yii\web\JsExpression;
use yii\helpers\Url;
/* @var $this yii\web\View */
/* @var $model common\models\LaboratorioNbuSnomed */
/* @var $form yii\widgets\ActiveForm */
?>

<div class="laboratorio-nbu-snomed-form">
    
    <?php $form = ActiveForm::begin(); ?>
    <table class="table">
        <tr>
            <th>NBU</th>
            <th>Snomed</th>
        </tr>
    <?php foreach ($dataProvider->query as $key => $value) { ?>
    <tr>
        <td>
    <?= $form->field($model, "[{$key}]codigo")->hiddenInput(['value'=> $value->codigo])->label(false);?>
    <?=  $value->nombre ?>
        </td>
        <td>
    <?= 
        $form->field($model, "[{$key}]conceptId")->widget(Select2::classname(), [
            'theme' => 'bootstrap',
            'language' => 'es',
            'options' => ['placeholder' => '-Seleccione la Práctica-'],
            'pluginOptions' => [
                'minimumInputLength' => 3,
                'ajax' => [
                    'url' => Url::to(['consultas/snomed-practicas']),
                    'dataType' => 'json',
                    'delay'=> 500,
                    'data' => new JsExpression('function(params) { return {q:params.term}; }')
                ],                                                
            ],
        ])->label(false)
    ?>
        </td>
    </tr>
    <?php 
        }
    ?>
    </table>
    <div class="form-group">
        <?= Html::submitButton('Guardar', ['class' => 'btn btn-success']) ?>
    </div>

    <?php ActiveForm::end(); ?>

    

</div>
