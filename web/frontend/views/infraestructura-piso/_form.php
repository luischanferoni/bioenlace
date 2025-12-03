<?php

use yii\helpers\Html;
use yii\bootstrap5\ActiveForm;

/* @var $this yii\web\View */
/* @var $model common\models\InfraestructuraPiso */
/* @var $form yii\widgets\ActiveForm */
?>

<div class="infraestructura-piso-form">

    <?php $form = ActiveForm::begin(); ?>


    <div class="row">
        <div class="col-sm-2"></div>
        <div class="col-sm">
            <div class="card">
                <div class="card-body">
                    
                    <?= $form->field($model, 'nro_piso')->textInput() ?>
                    <?= $form->field($model, 'descripcion')->textInput(['maxlength' => true]) ?>
                    <?= $form->field($model, 'id_efector')->hiddenInput(['value' => Yii::$app->user->getIdEfector()])->label(false) ?>

                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <?= Html::submitButton($model->isNewRecord ? 'Guardar' : 'Guardar cambios', ['class' => $model->isNewRecord ? 'btn btn-success rounded-pill' : 'btn btn-primary rounded-pill']) ?>
                        <?= Html::a('Cancelar', ['index'], ['class' => 'btn btn-danger rounded-pill']) ?>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-2"></div>
    </div>
</div>


<?php ActiveForm::end(); ?>

</div>