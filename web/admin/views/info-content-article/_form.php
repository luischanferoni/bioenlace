<?php

use yii\helpers\Html;
use yii\helpers\ArrayHelper;
use yii\widgets\ActiveForm;
use common\models\InfoContentArticle;
use common\models\Provincia;
use common\models\Efector;

/* @var $this yii\web\View */
/* @var $model common\models\InfoContentArticle */
/* @var $form yii\widgets\ActiveForm */
?>

<div class="info-content-article-form">

    <?php $form = ActiveForm::begin(); ?>

    <?= $form->field($model, 'topic')->textInput(['maxlength' => 80])
        ->hint('Clave temática única (ej. representacion, teleconsulta, turnos). Usar minúsculas y sin tildes.') ?>

    <?= $form->field($model, 'title')->textInput(['maxlength' => 255]) ?>

    <?= $form->field($model, 'body')->textarea(['rows' => 12])
        ->hint('Contenido en texto plano o markdown.') ?>

    <?= $form->field($model, 'scope')->dropDownList(InfoContentArticle::scopeLabels())
        ->hint('Producto = global. Provincia/Centro = override específico.') ?>

    <?= $form->field($model, 'id_provincia')->dropDownList(
        ArrayHelper::map(Provincia::find()->orderBy('nombre')->all(), 'id_provincia', 'nombre'),
        ['prompt' => '— Ninguna —']
    ) ?>

    <?= $form->field($model, 'id_efector')->dropDownList(
        ArrayHelper::map(Efector::find()->orderBy('nombre')->all(), 'id_efector', 'nombre'),
        ['prompt' => '— Ninguno —']
    ) ?>

    <?= $form->field($model, 'keywords')->textInput(['maxlength' => 500])
        ->hint('Palabras clave separadas por coma. El asistente usa estas para matchear el mensaje del usuario.') ?>

    <?= $form->field($model, 'priority')->textInput(['type' => 'number'])
        ->hint('Mayor número = más prioritario dentro del mismo scope.') ?>

    <?= $form->field($model, 'activo')->checkbox() ?>

    <div class="form-group">
        <?= Html::submitButton('Guardar', ['class' => 'btn btn-success']) ?>
    </div>

    <?php ActiveForm::end(); ?>

</div>
