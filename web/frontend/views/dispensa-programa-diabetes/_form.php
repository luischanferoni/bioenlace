<?php

use yii\helpers\Html;
use yii\widgets\ActiveForm;

/* @var $this yii\web\View */
/* @var $model common\models\DispensaProgramaDiabetes */
/* @var $form yii\widgets\ActiveForm */
?>

<div class="dispensa-programa-diabetes-form">

    <?php $form = ActiveForm::begin(); ?>

    <?= $form->field($model, 'id_persona_programa_diabetes')->textInput() ?>

    <?= $form->field($model, 'id_persona_retira')->textInput() ?>

    <?= $form->field($model, 'fecha_retiro')->textInput() ?>

    <?= $form->field($model, 'ins_lenta_nph')->textInput() ?>

    <?= $form->field($model, 'ins_lenta_lantus')->textInput() ?>

    <?= $form->field($model, 'ins_rapida_novorapid')->textInput() ?>

    <?= $form->field($model, 'metformina_500')->textInput() ?>

    <?= $form->field($model, 'metformina_850')->textInput() ?>

    <?= $form->field($model, 'glibenclamida')->textInput() ?>

    <?= $form->field($model, 'tiras')->dropDownList([ 50 => '50', 100 => '100', ], ['prompt' => '']) ?>

    <?= $form->field($model, 'monitor')->textInput() ?>

    <?= $form->field($model, 'lanceta')->textInput() ?>

    <?= $form->field($model, 'id_rrhh_efector')->textInput() ?>

    <div class="form-group">
        <?= Html::submitButton('Save', ['class' => 'btn btn-success']) ?>
    </div>

    <?php ActiveForm::end(); ?>

</div>
