<?php

use yii\helpers\Url;
use yii\helpers\Html;
use yii\web\JsExpression;
use yii\helpers\ArrayHelper;
use yii\web\View;

use common\models\ConsultaSintomas;

use kartik\select2\Select2;

?>

<!------ Formulario Síntomas ------->
<div class="form-card card mb-3 border border-2">
    <div class="card-header d-flex justify-content-between">
        <div class="header-title">
            <h4 class="card-title">Síntomas</h4>
        </div>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-lg-12">
                <div class="form-group">
                    <?php 
                    //echo var_dump($modelConsultaSintomas);exit;
                    $data = array();
                    $text= array();
                    if($idConsulta){
                        foreach($modelConsultaSintomas as $sintomas){
                            //echo var_dump($sintomas->codigoSnomed);
                            #if($sintomas['select2_codigo']){
                                $predata = ($sintomas->codigoSnomed) ? (array)($sintomas->codigoSnomed) : [];                   
                                $dat =  ArrayHelper::map($predata, 'conceptId', 'term') ;
                                
                                foreach($dat as $key => $value):
                                    $data[$key] =  $value;
                                    $text[] = $key;
                                endforeach;
                                array_pop($text);
                                array_pop($data);
                            #}
                        }    
                    }
                    //var_dump($data);
                    
                    #$data = isset($modelConsultaSintomas->codigoSnomed) ? ArrayHelper::map($modelConsultaSintomas->codigoSnomed, 'conceptId', 'nombre') : []; ?>

                    <?=
                        $form->field(new ConsultaSintomas, "select2_codigo")->widget(Select2::classname(), [
                            'data' => $data,
                            'size' => Select2::LARGE,
                            'options' => ['placeholder' => '- Escriba los Síntomas -', 'multiple' => 'true', 'id' => 'select_sintomas', 'value' => $text],
                            'pluginOptions' => [
                                'initialize' => true,
                                'minimumInputLength' => 4,
                                'ajax' => [
                                    'url' => Url::to(['snowstorm/sintomas']),
                                    'dataType' => 'json',
                                    'delay'=> 500,
                                    'data' => new JsExpression('function(params) { return {q:params.term}; }'),
                                    'cache' => true
                                ],
                            ],                            
                            'pluginEvents' => [
                                "select2:select" => 'function() {
                                    let i = 0;
                                    let sintomasSeleccionados = [];
                                    datos = $(this).select2("data");
                                    while (i < datos.length) {
                                        sintomasSeleccionados.push(datos[i].text);
                                        i++;
                                    }

                                    $("#terminos_sintomas").val(sintomasSeleccionados.join());
                                }',
                            ]
                        ])->label(false);
                    ?>
                    <?= Html::hiddenInput(
                            "terminos_sintomas", json_encode($data), ['id' => "terminos_sintomas"]);
                    ?>
                </div>
            </div>
        </div>
    </div>
</div>