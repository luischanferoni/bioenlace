<?php

use yii\helpers\Html;
use yii\bootstrap5\ActiveForm;
use common\models\InfraestructuraPiso;
use common\models\RrhhEfector;
use common\models\ServiciosEfector;
use kartik\select2\Select2;
use yii\helpers\ArrayHelper;

/* @var $this yii\web\View */
/* @var $model common\models\InfraestructuraSala */
/* @var $form yii\widgets\ActiveForm */
?>

<div class="infraestructura-sala-form">

    <?php $form = ActiveForm::begin(); ?>

    <div class="row">
        <div class="col-sm-2"></div>
        <div class="col-sm">
            <div class="card">
                <div class="card-body">
                    <?php
                    $infraestructuraPiso = new InfraestructuraPiso();
                    $pisos = $infraestructuraPiso->pisosPorEfector(Yii::$app->user->getIdEfector());
                    echo $form->field($model, 'id_piso')->widget(Select2::classname(), [
                        'data' => ArrayHelper::map($pisos, 'id', 'descripcion'),
                        'theme' => 'default',
                        'options' => ['placeholder' => 'Seleccione el Piso...'],
                        'pluginOptions' => [
                            'allowClear' => true,
                            'width' => '100%',
                        ],
                    ]);
                    ?>

                    <?= $form->field($model, 'nro_sala')->textInput() ?>

                    <?= $form->field($model, 'descripcion')->textInput(['maxlength' => true]) ?>

                    <?= $form->field($model, 'covid')->checkbox() ?>

                    <?php
                    $rrhh_Efector = new RrhhEfector();
                    $profesionales = $rrhh_Efector->obtenerMedicosPorEfector(yii::$app->user->getIdEfector());

                    echo $form->field($model, 'id_responsable')->widget(Select2::classname(), [
                        'data' => ArrayHelper::map($profesionales, 'id_rr_hh', 'datos'),
                        'theme' => 'default',
                        'language' => 'en',
                        'options' => ['placeholder' => 'Seleccione el Profesional...'],
                        'pluginOptions' => [
                            'allowClear' => true,
                            'width' => '100%',
                        ],
                    ]);
                    ?>

                    

                    <?php
                    $ServiciosEfector = new ServiciosEfector();
                    $servicios = $ServiciosEfector->serviciosPorEfector(Yii::$app->user->getIdEfector());
                    echo $form->field($model, 'id_servicio')->widget(Select2::classname(), [
                        'data' => ArrayHelper::map($servicios, 'id_servicio', 'servicio.nombre'),                        
                        'theme' => 'default',                        
                        'options' => ['placeholder' => 'Seleccione el Servicio...'],
                        'pluginOptions' => [
                            'allowClear' => true,
                            'width' => '100%',
                        ],
                    ]);
                    ?>

                    <?= $form->field($model, 'tipo_sala')->dropDownList(['femenino' => 'Femenino', 'masculino' => 'Masculino', 'indistinto' => 'Indistinto',], ['prompt' => '']) ?>

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