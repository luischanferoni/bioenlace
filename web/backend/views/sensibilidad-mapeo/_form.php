<?php

use yii\helpers\Html;
use yii\widgets\ActiveForm;

/* @var $this yii\web\View */
/* @var $model common\models\SensibilidadMapeoSnomed */
/* @var $categorias common\models\SensibilidadCategoria[] */
/* @var $form yii\widgets\ActiveForm */
?>

<div class="sensibilidad-mapeo-form">

    <?php $form = ActiveForm::begin(); ?>

    <?= $form->field($model, 'tabla_snomed')->dropDownList(\common\models\SensibilidadMapeoSnomed::TABLAS, ['prompt' => 'Seleccione tabla']) ?>
    <?= $form->field($model, 'codigo')->textInput(['maxlength' => true, 'placeholder' => 'conceptId SNOMED']) ?>
    <?= $form->field($model, 'id_categoria')->dropDownList(\yii\helpers\ArrayHelper::map($categorias, 'id', 'nombre'), ['prompt' => 'Seleccione categoría']) ?>

    <div class="form-group">
        <?= Html::submitButton($model->isNewRecord ? 'Crear' : 'Actualizar', ['class' => $model->isNewRecord ? 'btn btn-success' : 'btn btn-primary']) ?>
    </div>

    <?php ActiveForm::end(); ?>

</div>
