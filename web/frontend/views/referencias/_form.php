<?php
use yii\helpers\Html;
use yii\widgets\ActiveForm;
use yii\helpers\ArrayHelper;
use yii\helpers\Url;
use kartik\select2\Select2;
use kartik\depdrop\DepDrop;
use common\models\Efector;
use common\models\MotivoDerivacion;
use common\models\Servicio;

/* @var $this yii\web\View */
/* @var $model common\models\referencia */
/* @var $form yii\widgets\ActiveForm */

$url = Yii::$app->urlManager->createUrl('referencias/servicios');

$initScript = <<< SCRIPT
function (element, callback) {
    var id=$(element).val();
    var id_efector = $("#referencia-id_efector_referenciado").val();   
    if (id !== "") {
        $.ajax({method: "POST",
            url: "{$url}", 
            data: { id_servicio: id, id_efector_referenciado: id_efector},        
            dataType: "json"
        }).done(function(data) { callback(data.results);});
    }
}
SCRIPT;
?>
<script src="//code.jquery.com/jquery-1.10.2.js"></script>
 <style>
        div .alert{
            position: relative !important;
        }
    </style>
<div class="referencia-form">
    <?php $form = ActiveForm::begin(['options' => ['class' => 'form-horizontal', 'id' => 'form-referencias'],]); ?>
    <?php
    extract($_GET);
    
    ?>
    <div role="alert" class="alert alert-success">
        <p>Paciente: <strong><?php echo $persona[0]['apellido'].", ". $persona[0]['nombre']; ?></strong></p>
        <p>Turno: <strong><span class="glyphicon glyphicon-calendar"></span> <?php echo $persona[0]['fecha']." ". $persona[0 ]['hora'];?></strong></p>
    </div>
    
    <?php echo $form->field($model, 'id_consulta')->hiddenInput(['value' => $idc])->label(false); ?>

    <?php // $form->field($model, 'id_efector_referenciado')->textInput() ?>
    <div class="row">
        <?=
        Html::activeLabel($model, 'id_efector_referenciado', [
            'label' => 'Efector Referenciado: ',
            'class' => 'col-sm-2 control-label'
        ])
        ?>
        <div class="col-sm-4">
            <?php
            echo $form->field($model, 'id_efector_referenciado', [
                'template' => '{input}{error}{hint}'
            ])->widget(Select2::classname(), [
//                'data' => ArrayHelper::map(Efector::find()->all(), 'id_efector', 'nombre'),
                'data' => ArrayHelper::map(Efector::getEfectoresImplementados(), 'id_efector', 'nombre'),
                'options' => ['placeholder' => 'Seleccione una efector...'],
                'pluginOptions' => [
                    'allowClear' => true
                ],
            ]);
            ?>
        </div>
        <?=
        Html::activeLabel($model, 'id_motivo_derivacion', [
            'label' => 'Motivo de derivacion: ',
            'class' => 'col-sm-2 control-label'
        ])
        ?>
        <div class="col-sm-4">
            <?=
            $form->field($model, 'id_motivo_derivacion', [
                'template' => '{input}{error}{hint}'
            ])->widget(Select2::classname(), [
                'data' => ArrayHelper::map(MotivoDerivacion::find()->all(), 'id_motivo_derivacion', 'nombre'),
                'theme' => 'bootstrap',
                'language' => 'en',
                'options' => ['placeholder' => 'Seleccione el motivo de la derivacion...'],
                'pluginOptions' => [
                    'allowClear' => true
                ],
            ]);
            ?>
        </div>
    </div>
    <div class="row">
        <?=
        Html::activeLabel($model, 'id_servicio', [
            'label' => 'Servicio: ',
            'class' => 'col-sm-2 control-label'
        ])
        ?>

        <div class="col-sm-4">
            <?php
//          echo $form->field($model, 'id_servicio')->hiddenInput(['value'=> 5])->label(false);
            echo $form->field($model, 'id_servicio', [
                'template' => '{input}{error}{hint}'
            ])->widget(Select2::classname(), [
                'options' => ['placeholder' => 'Indique el servicio ...'],
                'pluginOptions' => [
                    'allowClear' => true,
                    'ajax' => [
                        'url' => $url,
                        'type' => 'POST',
                        'dataType' => 'json',
                        'data' => new \yii\web\JsExpression('function(term, page) { return {"id_efector_referenciado" : $("#referencia-id_efector_referenciado").val()}; }'),
                        'results' => new \yii\web\JsExpression('function(data,page) { return {results:data.results};}'),
                    ],
                    'initValueText' => new \yii\web\JsExpression($initScript)
                ],
            ]);
            ?>
        </div>
        <div  id="div_estcomp" style="display: none">
            <?=
            Html::activeLabel($model, 'estudios_complementarios', [
                'label' => 'Estudios Complementarios: ',
                'class' => 'col-sm-2 control-label'
            ])
            ?>
            <div class="col-sm-4">
                <?=
                $form->field($model, 'estudios_complementarios', [
                    'template' => '{input}{error}{hint}'
                ])->textarea(['rows' => 6])
                ?> 
            </div>
        </div>
    </div>

    <?php // $form->field($model, 'id_motivo_derivacion')->textInput(['maxlength' => true]) ?>
    <?php // $form->field($model, 'id_servicio')->textInput(['maxlength' => true]) ?>


    <div class="row">
        <?php // $form->field($model, 'tratamiento_previo')->dropDownList([ 'SI' => 'SI', 'NO' => 'NO', ], ['prompt' => '']) ?>
        <?=
        Html::activeLabel($model, 'tratamiento_previo', [
            'label' => 'Tratamiento Previo: ',
            'class' => 'col-sm-2 control-label'
        ])
        ?>
        <div class="col-sm-4">
            <?php
            echo $form->field($model, 'tratamiento_previo', ['template' => '{input}{error}{hint}'])
                    ->radioList([ 'SI' => 'SI', 'NO' => 'NO',], ['prompt' => '']);
            ?>
        </div>
        <div id="div_tratamiento" style="display: none">

            <?=
            Html::activeLabel($model, 'tratamiento', [
                'label' => 'Tratamiento: ',
                'class' => 'col-sm-2 control-label'
            ])
            ?>
            <div class="col-sm-4">
                <?= $form->field($model, 'tratamiento', ['template' => '{input}{error}{hint}'])->textarea(['rows' => 6]) ?>
            </div>
        </div>
    </div>

    <div class="row">
        <?=
        Html::activeLabel($model, 'resumen_hc', [
            //'label' => 'Historia Clinica: ',
            'label' => 'Diagnostico Presuntivo: ',
            'class' => 'col-sm-2 control-label'
        ])
        ?>
        <div class="col-sm-4">

            <?= $form->field($model, 'resumen_hc', ['template' => '{input}{error}{hint}'])->textarea(['rows' => 6]) ?>
        </div>
        <?=
        Html::activeLabel($model, 'observacion', [
            'label' => 'Observación: ',
            'class' => 'col-sm-2 control-label'
        ])
        ?>
        <div class="col-sm-4">

            <?= $form->field($model, 'observacion', ['template' => '{input}{error}{hint}'])->textarea(['rows' => 6]) ?>
        </div>
    </div>
    <?php //$form->field($model, 'id_estado')->textInput(['maxlength' => true]) ?>
    <?php
    // si es un alta el estado es 1 = Pendiente
    echo $form->field($model, 'id_estado')->hiddenInput(['value' => 1])->label(false);
    ?>
    <?php // $form->field($model, 'fecha_turno')->textInput() ?>

    <?php // $form->field($model, 'hora_turno')->textInput() ?>


    <div class="form-group">
        <?= Html::submitButton($model->isNewRecord ? 'Guardar' : 'Actualizar', ['class' => $model->isNewRecord ? 'btn btn-success' : 'btn btn-primary']) ?>
    </div>

    <?php ActiveForm::end(); ?>

</div>
<?php
$this->registerJsFile("js/referencias.js")
?>