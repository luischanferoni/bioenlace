<?php

use yii\helpers\Html;
use yii\widgets\ActiveForm;

/* @var $this yii\web\View */
/* @var $model common\models\domicilio */
/* @var $form yii\widgets\ActiveForm */
$localidades = \common\models\Localidad::find()->indexBy('id_localidad')->asArray()->all();
$lista_localidades = \yii\helpers\ArrayHelper::map($localidades, 'id_localidad', 'nombre');
?>
<style>
    .row {
        background-color:  #F7F7F7
    }
</style>
<div class="domicilio-form">
    <div class="page-header">
        <h2>Datos Domicilio</h2>
    </div>
    <?php $form = ActiveForm::begin(['options' => ['class' => 'form-horizontal'],]); ?>
    
    <div class="row">
        <?=
        Html::activeLabel($model, 'calle', [
            'label' => 'Calle: ',
            'class' => 'col-sm-2 control-label'
        ])
        ?>
        <div class="col-sm-4">
            <?=
            $form->field($model, 'calle', [
                'template' => '{input}{error}{hint}'
            ])->textInput(['maxlength' => true])
            ?>
        </div>
        <?=
        Html::activeLabel($model, 'numero', [
            'label' => 'Número: ',
            'class' => 'col-sm-2 control-label'
        ])
        ?>
        <div class="col-sm-4">
            <?=
            $form->field($model, 'numero', [
                'template' => '{input}{error}{hint}'
            ])->textInput(['maxlength' => true])
            ?>
        </div>
    </div>
    <div class="row">
         <?=
            Html::activeLabel($model, 'manzana', [
                'label' => 'Mzna: ',
                'class' => 'col-sm-2 control-label'
            ])
            ?>
            <div class="col-sm-2">
                <?=
                $form->field($model, 'manzana', [
                    'template' => '{input}{error}{hint}'
                ])->textInput(['maxlength' => true])
                ?>
            </div>
            <?=
            Html::activeLabel($model, 'lote', [
                'label' => 'Lote: ',
                'class' => 'col-sm-2 control-label'
            ])
            ?>
            <div class="col-sm-2">
                <?=
                $form->field($model, 'lote', [
                    'template' => '{input}{error}{hint}'
                ])->textInput(['maxlength' => true])
                ?>
            </div>
            <?=
            Html::activeLabel($model, 'sector', [
                'label' => 'Sector: ',
                'class' => 'col-sm-2 control-label'
            ])
            ?>
            <div class="col-sm-2">
                <?=
                $form->field($model, 'sector', [
                    'template' => '{input}{error}{hint}'
                ])->textInput(['maxlength' => true])
                ?>
            </div>
    </div>
    <div class="row">  
            <?=
            Html::activeLabel($model, 'grupo', [
                'label' => 'Grupo: ',
                'class' => 'col-sm-2 control-label'
            ])
            ?>
            <div class="col-sm-2">
            <?=
            $form->field($model, 'grupo', [
                'template' => '{input}{error}{hint}'
            ])->textInput(['maxlength' => true])
            ?>
            </div>
            <?=
            Html::activeLabel($model, 'torre', [
                'label' => 'Torre: ',
                'class' => 'col-sm-2 control-label'
            ])
            ?>
            <div class="col-sm-2">
            <?= $form->field($model, 'torre',[
                'template' => '{input}{error}{hint}'
            ])->textInput(['maxlength' => true]) 
            ?>
            </div>
            <?=
            Html::activeLabel($model, 'depto', [
                'label' => 'Depto: ',
                'class' => 'col-sm-2 control-label'
            ])
            ?>
            <div class="col-sm-2">
                <?= $form->field($model, 'depto',[
                    'template' => '{input}{error}{hint}'
                ])->textInput(['maxlength' => true]) ?>
            </div>
        </div>
    <div class="row">  
            <?=
            Html::activeLabel($model, 'barrio', [
                'label' => 'Barrio: ',
                'class' => 'col-sm-2 control-label'
            ])
            ?>
            <div class="col-sm-4">
                <?= $form->field($model, 'barrio',[
                        'template' => '{input}{error}{hint}'
                    ])->textInput(['maxlength' => true]) ?>
            </div>
            <?=
            Html::activeLabel($model, 'id_localidad', [
                'label' => 'Localidad: ',
                'class' => 'col-sm-2 control-label'
            ])
            ?>
            <div class="col-sm-4">
            <?= $form->field($model_localidad, 'id_localidad', [
                'template' => '{input}{error}{hint}'
                ])->dropDownList($lista_localidades, ['prompt' => 'Elija una opción...']); ?>
            </div>
</div>
    <div class="row">
            <?=
            Html::activeLabel($model, 'latitud', [
                'label' => 'Latitud: ',
                'class' => 'col-sm-2 control-label'
            ])
            ?>
    <div class="col-sm-2">
                    <?= $form->field($model, 'latitud',[
                        'template' => '{input}{error}{hint}'
                    ])->textInput(['maxlength' => true]) ?>
    </div>
 <?=
            Html::activeLabel($model, 'longitud', [
                'label' => 'Longitud: ',
                'class' => 'col-sm-2 control-label'
            ])
            ?>
    <div class="col-sm-2">
                    <?= $form->field($model, 'longitud',[
                        'template' => '{input}{error}{hint}'
                    ])->textInput(['maxlength' => true]) ?>
        </div>
<?=
            Html::activeLabel($model, 'urbano_rural', [
                'label' => 'Zona: ',
                'class' => 'col-sm-2 control-label'
            ])
            ?>
    <div class="col-sm-2">
                    <?= $form->field($model, 'urbano_rural', [
                'template' => '{input}{error}{hint}'
                ])->radioList([ 'U' => 'U', 'R' => 'R',], ['prompt' => 'Elija una opción...']) ?>
    </div>
</div> 
    

    <?php // $form->field($model, 'usuario_alta')->textInput(['maxlength' => true]) ?>

    <?php // $form->field($model, 'fecha_alta')->textInput() ?>
    
    <?php
    if ($model->isNewRecord){
        
    }else{
         $model_persona_domicilio = common\models\Persona_domicilio::findOne($model->id_domicilio);
      echo  $form->field($model_persona_domicilio, 'activo')->dropDownList([ 'SI' => 'Activo', 'NO' => 'Inactivo', ], [],['prompt' => ''])  ;
            //$form->dropDownList($model,'sex',array('1'=>'men','2'=>'women'), array('options' => array('2'=>array('selected'=>true))));
    }
    ?>

    <div class="form-group">
        <?= Html::submitButton($model->isNewRecord ? 'Agregar' : 'Actualizar', ['class' => $model->isNewRecord ? 'btn btn-success' : 'btn btn-primary']) ?>
    </div>

    <?php ActiveForm::end(); ?>

</div>
