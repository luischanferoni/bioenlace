<?php

use yii\helpers\Html;
use yii\widgets\ActiveForm;
use kartik\select2\Select2;
use kartik\depdrop\DepDrop;
use yii\helpers\ArrayHelper;
use yii\helpers\Url;
use common\models\InfraestructuraPiso;

/* @var $this yii\web\View */
/* @var $model common\models\InfraestructuraCama */
/* @var $form yii\widgets\ActiveForm */
?>

<div class="infraestructura-cama-form">

    <?php $form = ActiveForm::begin(); ?>

    <div class="row">
        <div class="col-sm-2"></div>
        <div class="col-sm">
            <div class="card">
                <div class="card-body">

                <?php
                    $infraestructuraPiso = new InfraestructuraPiso();
                    $pisos = $infraestructuraPiso->pisosPorEfector(Yii::$app->user->getIdEfector());
                    //$model->id_piso = $model->sala->piso->id;
                    echo $form->field($model, 'id_piso')->widget(Select2::classname(), [
                        'data' => ArrayHelper::map($pisos, 'id', 'descripcion'),
                        //'value' => isset($model->sala) ? $model->sala->piso->id : null,
                        'theme' => 'default',
                        'language' => 'es',
                        'options' => ['placeholder' => 'Seleccione el Piso...'],
                        'pluginOptions' => [
                            'width'=>'100%',
                            'allowClear' => true
                        ],
                    ]);
                    ?>
                    <?php
                    $dataSala =  $model->id_sala ? [$model->id_sala => $model->sala->descripcion] : [];
                    echo $form->field($model, 'id_sala')->widget(DepDrop::classname(), [
                        'data' => $dataSala,
                        'options' => ['id' => 'id_sala'],
                        'type' => DepDrop::TYPE_SELECT2,
                        'select2Options' => ['theme' => 'default', 'pluginOptions' => ['width' => '100%']],
                        'pluginOptions' => [
                            'depends' => ['infraestructuracama-id_piso'],
                            'placeholder' => 'Seleccione la Sala',
                            'url' => Url::to(['/infraestructura-sala/salas-por-piso'])
                        ]
                    ]);
                    ?>

                    <?= $form->field($model, 'nro_cama')->textInput() ?>

                    <?= $form->field($model, 'respirador')->checkbox() ?>

                    <?= $form->field($model, 'monitor')->checkbox() ?>

                    <?php //$form->field($model, 'id_sala')->textInput() 
                    ?>

                    <?=$form->field($model, 'estado')->hiddenInput(['value'=>'desocupada'])->label(false);?>

                    <?php /*
                    <?= $form->field($model, 'estado')->dropDownList(['ocupada' => 'Ocupada', 'desocupada' => 'Desocupada'], ['options' => ['desocupada'=>['Selected'=>'selected']]]) ?>

                    */?>

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