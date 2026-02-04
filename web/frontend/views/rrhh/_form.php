<?php

use yii\helpers\Html;
use yii\widgets\ActiveForm;
use yii\helpers\ArrayHelper;
use common\models\Efector;
use common\models\RrhhEfector;

/* @var $this yii\web\View */
/* @var $model common\models\RrhhEfector */
/* @var $model_persona common\models\Persona|null */
/* @var $form yii\widgets\ActiveForm */
?>
<div class="rrhh-form">

    <?php $form = ActiveForm::begin(); ?>

    <?php if ($model_persona): ?>
        <?= $form->field($model, 'id_persona')->hiddenInput(['value' => $model_persona->id_persona])->label(false) ?>
    <?php else: ?>
        <?= $form->field($model, 'id_persona')->textInput()->label('ID Persona') ?>
    <?php endif; ?>

    <div class="row">
        <div class="col-sm-6">
            <?php
            $id_efector = Yii::$app->user->idEfector ?? null;
            if ($id_efector !== null && $id_efector !== '' && $id_efector !== false) {
                echo $form->field($model, 'id_efector')->hiddenInput(['value' => $id_efector])->label(false);
                echo '<label class="control-label">Efector:</label> ' . (Yii::$app->user->nombreEfector ?? $id_efector);
            } else {
                $efectores = ArrayHelper::map(Efector::find()->asArray()->all(), 'id_efector', 'nombre');
                echo $form->field($model, 'id_efector')->dropDownList($efectores, [
                    'id' => 'id_efector',
                    'prompt' => ' -- Elija un Efector --',
                ]);
            }
            ?>
        </div>
    </div>

    <div class="form-group">
        <?= Html::submitButton($model->isNewRecord ? 'Crear' : 'Modificar', ['class' => $model->isNewRecord ? 'btn btn-success' : 'btn btn-primary']) ?>
    </div>

    <?php ActiveForm::end(); ?>

</div>
