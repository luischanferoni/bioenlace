<?php

use common\models\Provincia;
use kartik\depdrop\DepDrop;
use kartik\select2\Select2;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;
use yii\helpers\Url;
use yii\widgets\ActiveForm;

/* @var $this yii\web\View */
/* @var $model common\models\Efector */
/* @var $form yii\widgets\ActiveForm */

$localidad = $model->localidad;
$departamento = $localidad->departamento ?? null;
$idProvinciaActual = $departamento->id_provincia ?? null;
$provincias = ArrayHelper::map(
    Provincia::find()->orderBy(['nombre' => SORT_ASC])->asArray()->all(),
    'id_provincia',
    'nombre'
);
$localidadData = [];
if ($model->id_localidad && $localidad !== null) {
    $localidadData = [(int) $model->id_localidad => (string) $localidad->nombre];
}
?>

<div class="efector-form">

    <?php $form = ActiveForm::begin(); ?>

    <?= $form->field($model, 'codigo_sisa')->textInput(['maxlength' => true,'readonly' =>true ]) ?>

    <?= $form->field($model, 'nombre')->textInput(['maxlength' => true,'readonly' =>true]) ?>

    <?= $form->field($model, 'dependencia')->textInput(['maxlength' => true,'readonly' =>true]) ?>

    <?= $form->field($model, 'tipologia')->textInput(['maxlength' => true,'readonly' =>true]) ?>

    <?= $form->field($model, 'domicilio')->textInput(['maxlength' => true]) ?>

    <?= $form->field($model, 'grupo')->textInput(['maxlength' => true]) ?>

    <?= $form->field($model, 'formas_acceso')->textInput(['maxlength' => true]) ?>

    <?= $form->field($model, 'telefono')->textInput(['maxlength' => true]) ?>

    <?= $form->field($model, 'telefono2')->textInput(['maxlength' => true]) ?>

    <?= $form->field($model, 'telefono3')->textInput(['maxlength' => true]) ?>

    <?= $form->field($model, 'mail1')->textInput(['maxlength' => true]) ?>

    <?= $form->field($model, 'mail2')->textInput(['maxlength' => true]) ?>

    <?= $form->field($model, 'mail3')->textInput(['maxlength' => true]) ?>

    <?= $form->field($model, 'dias_horario')->textInput(['maxlength' => true]) ?>

    <?= $form->field($model, 'origen_financiamiento')->textInput(['maxlength' => true,'readonly' =>true]) ?>

    <div class="form-group">
        <label class="control-label" for="efector-id_provincia">Provincia</label>
        <?= Select2::widget([
            'name' => 'id_provincia',
            'id' => 'efector-id_provincia',
            'value' => $idProvinciaActual,
            'data' => $provincias,
            'theme' => Select2::THEME_DEFAULT,
            'options' => [
                'placeholder' => 'Seleccione provincia',
            ],
            'pluginOptions' => [
                'allowClear' => true,
                'width' => '100%',
            ],
        ]) ?>
    </div>

    <?= $form->field($model, 'id_localidad')->widget(DepDrop::classname(), [
        'type' => DepDrop::TYPE_SELECT2,
        'data' => $localidadData,
        'options' => [
            'id' => 'efector-id_localidad',
            'placeholder' => 'Seleccione localidad',
        ],
        'select2Options' => [
            'theme' => Select2::THEME_DEFAULT,
            'pluginOptions' => ['width' => '100%'],
        ],
        'pluginOptions' => [
            'depends' => ['efector-id_provincia'],
            'placeholder' => 'Seleccione localidad',
            'url' => Url::to(['/geografia/localidades-por-provincia-depdrop']),
            'initialize' => $idProvinciaActual !== null,
            'params' => ['efector-id_localidad-selected'],
        ],
    ]) ?>
    <?= Html::hiddenInput('efector-id_localidad-selected', $model->id_localidad, [
        'id' => 'efector-id_localidad-selected',
    ]) ?>

    <?= $form->field($model, 'estado')->dropDownList([ 'ACTIVO' => 'ACTIVO', 'INACTIVO' => 'INACTIVO', ], ['prompt' => '','readonly' =>true]) ?>

    <div class="form-group">
        <?= Html::submitButton($model->isNewRecord ? 'Guardar' : 'Modificar', ['class' => $model->isNewRecord ? 'btn btn-success' : 'btn btn-primary']) ?>
    </div>

    <?php ActiveForm::end(); ?>

</div>
