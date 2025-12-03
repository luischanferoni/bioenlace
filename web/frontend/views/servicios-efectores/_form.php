<?php

use yii\helpers\Html;
use yii\bootstrap5\ActiveForm;
use kartik\select2\Select2;
use yii\helpers\ArrayHelper;

use common\models\Efector;
use common\models\Servicio;
use common\models\ServiciosEfector;
/* @var $this yii\web\View */
/* @var $model common\models\ServiciosEfector */
/* @var $form yii\widgets\ActiveForm */
?>

<?php 
    
    $servicios = Servicio::find()
        ->rightJoin('servicios_efector', 'servicios_efector.id_servicio = servicios.id_servicio')
        //->andWhere(['servicios_efector.formas_atencion' => ServiciosEfector::ORDEN_LLEGADA_PARA_TODOS])
        ->andWhere(['servicios_efector.pase_previo' => 0])
        ->andWhere(['servicios_efector.id_efector' => Yii::$app->user->getIdEfector()])
        ->andWhere(['LIKE','servicios.acepta_turnos','SI'])
        ->asArray()->all();
    
    $data_servicios = ArrayHelper::map($servicios, 'id_servicio', 'nombre');

?>

<?php $form = ActiveForm::begin(); ?>

<?= Html::hiddenInput("id_efector", $model->id_efector);?>

<div class="card">
    <div class="card-header d-flex justify-content-between">
        <div class="header-title">
            <h4 class="card-title"><?=$this->title;?></h4>
        </div>
    </div>

    <div class="card-body" id="dynamic-servicio-form">
        <div class="row">
      
            <div class="col">
                <?=
                    $form->field($model, 'id_servicio')
                        ->widget(Select2::classname(), [
                            'data' => ArrayHelper::map(Servicio::find()->all(), 'id_servicio','nombre'),
                            'theme' => Select2::THEME_DEFAULT,
                            'options' => ['placeholder' => 'Seleccione un servicio'],
                            'pluginOptions' => [
                                'allowClear' => true,
                                'disabled' => $model->isNewRecord ? false : true,
                                'width' => '100%'
                                ],
                            ]);
                ?>
            </div>

            <div class="col">                            
                <?= $form->field($model, "formas_atencion")
                                        ->DropDownList(ServiciosEfector::FORMAS_ATENCION, 
                                        [
                                        'data-bs-toggle' => 'tooltip',
                                        'data-bs-placement' => 'top',
                                        'data-bs-original-title' => 'Formas de atención para todo el servicio'                                                    
                                        ]) ?>
            </div>

            <div class="col">                            
                <?= 
                    $form->field($model, "pase_previo")->widget(
                        Select2::classname(), [
                            'data' => $data_servicios,
                            'theme' => Select2::THEME_DEFAULT,
                            'language' => 'es',
                            'options' => ['placeholder' => '', 
                                    
                                        'data-bs-toggle' => 'tooltip',
                                        'data-bs-placement' => 'top',
                                        'data-bs-original-title' => 'Formas de atención para todo el servicio'
                                    ],
                            'pluginOptions' => [
                                'allowClear' => true,
                                'width' => '100%',
                                'data-bs-toggle' => 'tooltip',
                                'data-bs-placement' => 'top',
                                'data-bs-original-title' => 'Formas de atención para todo el servicio'                           
                            ],
                        ]);
                ?>
            </div>            

            <div class="form-group">
                <?= Html::submitButton($model->isNewRecord ? 'Guardar' : 'Modificar', ['class' => ($model->isNewRecord ? 'btn btn-success' : 'btn btn-primary').' float-end']) ?>
            </div>
        </div>
    </div>
</div>

<?php ActiveForm::end(); ?>
