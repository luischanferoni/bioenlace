<?php

use yii\helpers\Html;
use yii\widgets\ActiveForm;
use wbraganca\dynamicform\DynamicFormWidget;
use kartik\select2\Select2;
use yii\web\JsExpression;
use yii\helpers\Url;

/* @var $this yii\web\View */
/* @var $model common\models\SegNivelInternacionMedicamento */
/* @var $form yii\widgets\ActiveForm */
?>

<div class="seg-nivel-internacion-medicamento-form">

    <?php $form = ActiveForm::begin(); ?>
    
    <table class="table table-bordered table-striped margin-b-none">
    <thead>
        <tr>
            <th class="required">Concepto</th>
            <th class="required">Cantidad</th>   
            <th class="required">Dosis Diaria</th>            
        </tr>
    </thead>
    <tbody  class="container-items">       
                <tr class="item">                      
                    <td> 
                    <?php $data = !$model->medicamentoSnomed ? [] : [$model->conceptId => $model->medicamentoSnomed->term]; ?>
                        <?= 
                            $form->field($model, "conceptId")->widget(Select2::classname(), [
                                'data'=> $data,
                                'theme' => 'bootstrap',
                                'language' => 'es',
                                'options' => ['placeholder' => '-Seleccione el Medicamento-'],
                                'pluginOptions' => [
                                    'minimumInputLength' => 3,
                                        'ajax' => [
                                            'url' => Url::to(['consultas/snomed']),
                                            'dataType' => 'json',
                                            'delay'=> 500,
                                            'data' => new JsExpression('function(params) { return {q:params.term}; }')
                                        ],                                                
                                ],
                            ])->label(false)

                        ?>
                    </td>
                    <td>
                        <?= $form->field($model, "cantidad")->textInput()->label(false) ?>
                    </td>
                    <td>
                        <?= $form->field($model, "dosis_diaria")->textInput(["maxlength" => true])->label(false) ?>                        
                    </td>
                  
                    
                </tr>
            
        </tbody>    
                </table>

    <div class="form-group">
        <?= Html::submitButton($model->isNewRecord ? 'Guardar' : 'Guardar cambios', ['class' => $model->isNewRecord ? 'btn btn-success' : 'btn btn-primary']) ?>
        <?= Html::a('Cancelar', ['internacion/view', 'id'=> $model->id_internacion ], ['class' => 'btn btn-danger']) ?>
    </div>

    <?php ActiveForm::end(); ?>

</div>
