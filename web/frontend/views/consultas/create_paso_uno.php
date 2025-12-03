<?php

use yii\helpers\Html;

use yii\widgets\ActiveForm;
use yii\helpers\Url;
use yii\web\JsExpression;

use kartik\select2\Select2;
use yii\helpers\ArrayHelper;
use common\models\ConsultaMotivos;

?>

<?php yii\widgets\Pjax::begin([
    'id' => 'submit_motivo_consulta',
    'enablePushState' => false, 'enableReplaceState' => false
]) ?>
<div class="row">
    <div class="col">
        <div class="card">
            <div class="card-header d-flex justify-content-between">
                <div class="header-title">

                    <?php switch ($model->parent_class) {
                        case 'Turno':
                            echo '<h4 class="card-title">Seleccione el motivo de la consulta</h4>';
                            break;

                        case 'SegNivelInternacion':
                            echo '<h4 class="card-title">Seleccione el motivo de la internación</h4>';
                            break;
                    } ?>
                </div>
            </div>
            <div class="card-body">

                <?php
                switch ($model->parent_class) {
                    case 'Turno':
                        $form = ActiveForm::begin(
                            [
                                'action' => ['consultas/create-paso-dos'],
                                'id' => 'form-motivoconsulta',
                                'options' => []
                            ]
                        );
                        break;

                        case 'Consulta':
                            $action = (!$model->parent_id)? 'consultas/create-paso-dos': 'consultas/create-paso-dos?id_consulta='.$model->parent_id;
                            $form = ActiveForm::begin(
                                [
                                    'action' => [$action],
                                    'id' => 'form-motivoconsulta',
                                    'options' => []
                                ]
                            );
                        break;   

                    case 'SegNivelInternacion':
                        $form = ActiveForm::begin(
                            [
                                'action' => ['consultas/create-paso-dos-internacion'],
                                'id' => 'form-motivoconsulta',
                                'options' => []
                            ]
                        );
                        break;
                } ?>

                <?= $form->field($model, 'parent_class')->hiddenInput()->label(false); ?>
                <?= $form->field($model, 'parent_id')->hiddenInput()->label(false); ?>
                <?= $form->field($model, 'id_persona')->hiddenInput()->label(false); ?>

                <div class="form-group">
                    <?php /*
                    $form->field($model, "codigo_motivo_consulta")->widget(Select2::classname(), [
                        'size' => Select2::LARGE,
                        'options' => ['placeholder' => '-Seleccione el motivo de la consulta-'],
                        'pluginOptions' => [
                            'minimumInputLength' => 3,
                            'ajax' => [
                                'url' => Url::to(['snowstorm/motivos-de-consulta']),
                                'dataType' => 'json',
                                'data' => new JsExpression('function(params) { return {q:params.term}; }')
                            ],
                        ],
                    ])->label(false)*/
                    ?>
                    <?php 
                    $data = array();
                    $text= array();
                    if($idConsulta){
                        foreach($modelMotivosConsulta as $motivo){
                            $predata = ($motivo->codigoSnomed) ? (array)($motivo->codigoSnomed) : [];                   
                            $dat =  ArrayHelper::map($predata, 'conceptId', 'term') ;
                            
                            foreach($dat as $key => $value):
                                $data[$key] =  $value;
                                $text[] = $key;
                            endforeach;
                            array_pop($text);
                            array_pop($data);
                        }
                    }
                   
                     #echo json_encode($valor).'<br>'.json_encode($text).'<br>'; 
                     #echo var_dump($valor).'<br>'.var_dump($text);
                     #exit;
                     #echo var_dump($data).'<br>'.var_dump($valor);die();
                    ?>
                    
                    <?=
                        $form->field(new ConsultaMotivos(), "select2_codigo")->widget(Select2::classname(), [
                            'data' => $data, //[[1=> 'hola'],[2=> 'chau']],
                            'size' => Select2::LARGE,
                            'options' => ['placeholder' => '- Escriba los Motivos -', 'multiple' => 'true', 'id' => 'select_motivos', 'value' => $text],
                            'pluginOptions' => [
                                'initialize' => true,
                                'minimumInputLength' => 4,
                                'ajax' => [
                                    'url' => Url::to(['snowstorm/motivos-de-consulta']),
                                    'dataType' => 'json',
                                    'data' => new JsExpression('function(params) { return {q:params.term}; }'),
                                    'cache' => true
                                ],
                            ],                            
                            'pluginEvents' => [
                                "select2:select" => 'function() {
                                    let i = 0;
                                    let motivosSeleccionados = [];
                                    datos = $(this).select2("data");
                                    while (i < datos.length) {
                                        motivosSeleccionados.push(datos[i].text);
                                        i++;
                                    }

                                    $("#terminos_motivos").val(motivosSeleccionados.join());
                                }',
                            ]
                        ])->label(false);
                    ?>
                    
                    <?= Html::hiddenInput(
                            "ConsultaMotivos[terminos_motivos]", json_encode($data), ['id' => "terminos_motivos"]);
                    ?>                    
                </div>

                <?php if ($paciente->edad < 50 && $paciente->edad >= 10 && $paciente->sexo_biologico == 1) { ?>
                    <div class="form-group">
                        <label class="btn btn-outline-primary">
                            <input type="checkbox" name="embarazo" autocomplete="off"> Embarazo
                        </label>
                    </div>
                <?php } ?>

                <button type="submit" class="btn btn-primary float-end" id="submit_paso_uno" data-form="" data-pjax="true">Siguiente</button>

                <?php ActiveForm::end(); ?>

            </div>
        </div>
    </div>
</div>

<?php yii\widgets\Pjax::end() ?>
<?php
$this->registerJs(
    "
        $(document).on('pjax:send', function(event) {
            $('#submit_paso_uno').attr('disabled', true);
            $('#submit_paso_uno').html('Siguiente ...');
        });
        $(document).on('pjax:complete', function() {
            $('#submit_paso_uno').attr('disabled', false);
            $('#submit_paso_uno').html('Siguiente');
        });
    "
);
