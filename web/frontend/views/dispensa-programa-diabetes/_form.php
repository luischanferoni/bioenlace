<?php

use yii\helpers\Html;
use yii\helpers\Url;
use yii\widgets\ActiveForm;
use kartik\depdrop\DepDrop;

/* @var $this yii\web\View */
/* @var $model common\models\DispensaProgramaDiabetes */
/* @var $form yii\widgets\ActiveForm */
?>

<div class="dispensa-programa-diabetes-form">

    <?php $form = ActiveForm::begin(); ?>

    <?= $form->field($model, 'id_persona_programa_diabetes')->textInput() ?>

    <?= $form->field($model, 'id_profesional_efector_servicio')->widget(DepDrop::className(), [
        'type' => DepDrop::TYPE_SELECT2,
        'select2Options' => ['theme' => 'default'],
        'pluginOptions' => [
            'depends' => ['dispensaprogramadiabetes-id_persona_programa_diabetes'],
            'placeholder' => 'Seleccione profesional que entrega',
            'url' => Url::to(['/profesional-en-efector/profesionales-por-persona-programa-diabetes']),
        ],
    ]) ?>

    <?= $form->field($model, 'id_persona_retira')->textInput() ?>

    <?= $form->field($model, 'fecha_retiro')->textInput() ?>

    <?= $form->field($model, 'ins_lenta_nph')->textInput() ?>

    <?= $form->field($model, 'ins_lenta_lantus')->textInput() ?>

    <?= $form->field($model, 'ins_rapida_novorapid')->textInput() ?>

    <?= $form->field($model, 'metformina_500')->textInput() ?>

    <?= $form->field($model, 'metformina_850')->textInput() ?>

    <?= $form->field($model, 'glibenclamida')->textInput() ?>

    <?= $form->field($model, 'tiras')->dropDownList([50 => '50', 100 => '100'], ['prompt' => '']) ?>

    <?= $form->field($model, 'monitor')->textInput() ?>

    <?= $form->field($model, 'lanceta')->textInput() ?>

    <div class="form-group">
        <?= Html::submitButton('Save', ['class' => 'btn btn-success']) ?>
    </div>

    <?php ActiveForm::end(); ?>

</div>
