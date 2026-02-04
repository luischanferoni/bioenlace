<?php

use yii\helpers\Html;
use yii\widgets\ActiveForm;
use common\models\InfraestructuraPiso;
use common\models\RrhhEfector;
use common\models\Servicios_efector;
use kartik\select2\Select2;
use yii\helpers\ArrayHelper;

/* @var $this yii\web\View */
/* @var $model common\models\InfraestructuraSala */
/* @var $form yii\widgets\ActiveForm */
?>

<div class="infraestructura-sala-form">

    <?php $form = ActiveForm::begin(); ?>


    <?= $form->field($model, 'nro_sala')->textInput() ?>

    <?= $form->field($model, 'descripcion')->textInput(['maxlength' => true]) ?>

    <?= $form->field($model, 'covid')->checkbox() ?>

    <?php //$form->field($model, 'id_responsable')->textInput() ?>
    <?php 
            $rrhh_Efector= new RrhhEfector();
            $profesionales = $rrhh_Efector->obtenerProfesionalesPorEfector(yii::$app->user->getIdEfector());

            echo $form->field($model, 'id_responsable')->widget(Select2::classname(), [
                'data' => ArrayHelper::map($profesionales, 'id_rr_hh', 'datos'),
                'theme' => 'bootstrap',
                'language' => 'en',
                'options' => ['placeholder' => 'Seleccione el Profesional...'],
                'pluginOptions' => [
                    'allowClear' => true
                ],
            ]);
            ?>

    <?php //$form->field($model, 'id_piso')->textInput() ?>
    <?php 
            $infraestructuraPiso= new InfraestructuraPiso();
            $pisos = $infraestructuraPiso->pisosPorEfector(Yii::$app->user->getIdEfector());
            echo $form->field($model, 'id_piso')->widget(Select2::classname(), [
                'data' => ArrayHelper::map($pisos, 'id', 'descripcion'),
                'theme' => 'bootstrap',
                'language' => 'en',
                'options' => ['placeholder' => 'Seleccione el Piso...'],
                'pluginOptions' => [
                    'allowClear' => true
                ],
            ]);
            ?>

    <?php //$form->field($model, 'id_servicio')->textInput() ?>
    <?php 
            $servicios_efector = new Servicios_efector();
            $servicios = $servicios_efector->serviciosPorEfector(Yii::$app->user->getIdEfector());
            echo $form->field($model, 'id_servicio')->widget(Select2::classname(), [
                'data' => ArrayHelper::map($servicios, 'id_servicio', 'servicio.nombre'),
                'theme' => 'bootstrap',
                'language' => 'en',
                'options' => ['placeholder' => 'Seleccione el Servicio...'],
                'pluginOptions' => [
                    'allowClear' => true
                ],
            ]);
            ?>

    <?= $form->field($model, 'tipo_sala')->dropDownList([ 'femenino' => 'Femenino', 'masculino' => 'Masculino', 'indistinto' => 'Indistinto', ], ['prompt' => '']) ?>

    <div class="form-group">
        <?= Html::submitButton($model->isNewRecord ? 'Guardar' : 'Guardar cambios', ['class' => $model->isNewRecord ? 'btn btn-success' : 'btn btn-primary']) ?>
        <?= Html::a('Cancelar', ['index'], ['class' => 'btn btn-danger']) ?>
    </div>


    <?php ActiveForm::end(); ?>

</div>
