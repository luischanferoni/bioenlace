<?php

use yii\helpers\Html;
use yii\widgets\ActiveForm;
use kartik\select2\Select2;
use yii\web\JsExpression;
use common\models\Rrhh_efector;
use yii\helpers\ArrayHelper;
use yii\helpers\Url;

/* @var $this yii\web\View */
/* @var $model common\models\SegNivelInternacionPractica */
/* @var $form yii\widgets\ActiveForm */
?>
<style type="text/css">
select[readonly].select2-hidden-accessible + .select2-container {
  pointer-events: none;
  touch-action: none;

  .select2-selection {
    background: #eee;
    box-shadow: none;
  }

  .select2-selection__arrow,
  .select2-selection__clear {
    display: none;
  }
}
</style>
<div class="seg-nivel-internacion-practica-form">

    <?php $form = ActiveForm::begin(['options' => ["enctype" => "multipart/form-data"]]); ?>
    <?php $data = !$model->practicaSnomed ? [] : [$model->conceptId => $model->practicaSnomed->term]; ?>
    <?= 
        $form->field($model, "conceptId")->widget(Select2::classname(), [
            'data'=> $data,
            'theme' => 'bootstrap',
            'language' => 'es',
            'readonly'=> true,
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
        ])

        ?>
    <?php 
        $rrhh_Efector= new Rrhh_efector();
        $profesionales = $rrhh_Efector->obtenerProfesionalesPorEfector(yii::$app->user->getIdEfector());

        echo $form->field($model, 'id_rrhh_solicita')->widget(Select2::classname(), [
            'data' => ArrayHelper::map($profesionales, 'id_rr_hh', 'datos'),
            'theme' => 'bootstrap',
            'language' => 'en',
            'readonly'=> true,
            'options' => ['placeholder' => 'Seleccione el Profesional que solicita'],
            'pluginOptions' => [
                'allowClear' => true                
            ],
        ]);
    ?>        
    <?= $form->field($model, 'resultado')->textInput(['maxlength' => true]) ?>

    <?= $form->field($model, 'informe')->textarea(['rows' => 6]) ?>

    <?= $form->field($model, 'imageFile')->fileInput() ?>

<?php 
        $rrhh_Efector= new Rrhh_efector();
        $profesionales = $rrhh_Efector->obtenerProfesionalesPorEfector(yii::$app->user->getIdEfector());

        echo $form->field($model, 'id_rrhh_realiza')->widget(Select2::classname(), [
            'data' => ArrayHelper::map($profesionales, 'id_rr_hh', 'datos'),
            'theme' => 'bootstrap',
            'language' => 'en',
            'options' => ['placeholder' => 'Seleccione el Profesional que realiza la práctica'],
            'pluginOptions' => [
                'allowClear' => true
            ],
        ]);
    ?>

    <div class="form-group">
        <?= Html::submitButton($model->isNewRecord ? 'Guardar' : 'Guardar cambios', ['class' => $model->isNewRecord ? 'btn btn-success' : 'btn btn-primary']) ?>
        <?= Html::a('Cancelar', ['internacion/view', 'id'=> $model->id_internacion ], ['class' => 'btn btn-danger']) ?>
    </div>

    <?php ActiveForm::end(); ?>

</div>
