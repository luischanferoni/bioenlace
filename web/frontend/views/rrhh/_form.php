<?php

use yii\helpers\Html;
use yii\widgets\ActiveForm;
use yii\helpers\ArrayHelper;
use yii\helpers\Url;

use kartik\depdrop\DepDrop;

use common\models\Profesiones;
use common\models\Especialidades;
use common\models\Efector;
use common\models\Servicios;
use common\models\Rrhh;
use common\models\Condiciones_laborales;

/* @var $this yii\web\View */
/* @var $model common\models\Rrhh */
/* @var $form yii\widgets\ActiveForm */
extract($_GET);
?>

<div class="rrhh-form">

    <?php $form = ActiveForm::begin(); ?>

    <?php
        echo $form->field($model, 'id_persona')->hiddenInput(['value'=> $idp])->label(false);
    ?>

    <div class="row">
        <?=
        Html::activeLabel($model, 'id_profesion', [
            'label' => 'Profesion: ',
            'class' => 'col-sm-2 control-label'
        ])
        ?>
        <div class="col-sm-4">
            <?php
            $profesiones = ArrayHelper::map(Profesiones::find()->asArray()->all(), 'id_profesion', 'nombre');
            echo $form->field($model, 'id_profesion', [
                'template' => '{input}{error}{hint}'])->dropDownList($profesiones, [ 'id' => 'id_profesion',
                'prompt' => ' -- Elija una Profesion --',
            ]);
            ?>
        </div>
        <?=
        Html::activeLabel($model, 'id_especialidad', [
            'label' => 'Especialidad: ',
            'class' => 'col-sm-2 control-label'
        ])
        ?>
        <div class="col-sm-4">
            <?php
            echo $form->field($model, 'id_especialidad', [
                'template' => '{input}{error}{hint}'])->widget(DepDrop::classname(), [
                'options' => ['id' => 'id_especialidad'],
                'pluginOptions' => [
                    'depends' => ['id_profesion'],
                    'placeholder' => 'Seleccione Especialidad',
                    'url' => Url::to(['/rrhh/subcat'])
                ]
            ]);
            ?>
        </div>
    </div>
    <div class="row">
        <?=
        Html::activeLabel($model_rr_hh_efector, 'id_efector', [
            'label' => 'Efector: ',
            'class' => 'col-sm-2 control-label'
        ])
        ?>
        <div class="col-sm-4">           
            <?php
            $id_efector= Yii::$app->user->idEfector;
            if(isset($id_efector) && ($id_efector !='' && $id_efector != null)){
                echo $form->field($model_rr_hh_efector, 'id_efector')->hiddenInput(['value'=> $id_efector])->label(false);
                echo Yii::$app->user->nombreEfector;
            } else {
                $efectores = ArrayHelper::map(Efector::find()->asArray()->all(), 'id_efector', 'nombre');
                echo $form->field($model_rr_hh_efector, 'id_efector', [
                    'template' => '{input}{error}{hint}'])->dropDownList($efectores, [ 'id' => 'id_efector',
                    'prompt' => ' -- Elija un Efector --',
                ]);
            }
            ?>
        </div>
        <?=
        Html::activeLabel($model_rr_hh_efector, 'id_servicio', [
            'label' => 'Servicio: ',
            'class' => 'col-sm-2 control-label'
        ])
        ?>
        <div class="col-sm-4">
            <?php
            $model_rrhh = new Rrhh;
            if(isset($id_efector) && ($id_efector !='' && $id_efector != null)){
                $ServiciosEfector = ArrayHelper::map($model_rrhh->getListaServiciosXefector($id_efector), 'id', 'name');

                echo $form->field($model_rr_hh_efector, 'id_servicio', [
                    'template' => '{input}{error}{hint}'])->dropDownList($ServiciosEfector, [ 'id_servicio'=>'id',
                    'prompt' => ' -- Elija un Servicio --',
                ]);
            } else {
                echo $form->field($model_rr_hh_efector, 'id_servicio', [
                'template' => '{input}{error}{hint}'])->widget(DepDrop::classname(), [
                //  'options' => ['id' => 'id_departamento', 'name' => 'nombre'],
                'options' => ['id' => 'id_servicio'],
                'pluginOptions' => [
                    'depends' => ['id_efector'],
                    'placeholder' => 'Seleccione Servicio',
                    'url' => Url::to(['/rrhh/subcatservicios'])
                ]
                ]);
            }
            
            ?>
        </div>
        
        

    </div>
    <div class="row">
        
        <?=
        Html::activeLabel($model_rr_hh_efector, 'id_condicion_laboral', [
            'label' => 'Condicion Laboral: ',
            'class' => 'col-sm-2 control-label'
        ])
        ?>
        <div class="col-sm-4">
            <?php
            $condiciones = ArrayHelper::map(Condiciones_laborales::find()->asArray()->all(), 'id_condicion_laboral', 'nombre');
            echo $form->field($model_rr_hh_efector, 'id_condicion_laboral', [
                'template' => "{input}{error}{hint}"
            ])->dropDownList($condiciones,
                    ['prompt' => ' -- Elija Condicion Laboral --']);
            ?>
        </div>
        
        <?=
        Html::activeLabel($model_rr_hh_efector, 'horario', [
            'label' => 'Horario: ',
            'class' => 'col-sm-2 control-label'
        ])
        ?>
        <div class="col-sm-4">
            <?php
            if ($model->isNewRecord) { 
                echo $form->field($model_rr_hh_efector, 'horario', [
                    'template' => '{input}{error}{hint}'
                    ])->textInput(['maxlength' => true,'value'=> (isset($_POST["Rrhh_efector"]["horario"]))?$_POST["Rrhh_efector"]["horario"]:'',]);
            }else{
                echo $form->field($model_rr_hh_efector, 'horario', [
                    'template' => '{input}{error}{hint}'
                    ])->textInput(['maxlength' => true,]);
            }
            ?>
        </div>
       

    </div>
    <div class="form-group">
        <?= Html::submitButton($model->isNewRecord ? 'Crear' : 'Modificar', ['class' => $model->isNewRecord ? 'btn btn-success' : 'btn btn-primary']) ?>
    </div>

    <?php ActiveForm::end(); ?>

</div>
