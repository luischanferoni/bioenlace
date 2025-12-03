<?php
/*
 * Autor: Guillermo Ponce
 * Creado: 16/10/2015
 * Modificado: 
*/

use yii\helpers\Html;
use yii\helpers\ArrayHelper;
use yii\widgets\ActiveForm;
use yii\helpers\Url;
use common\models\Provincia;
use common\models\Departamento;
//use common\models\Localidad;

/* @var $this yii\web\View */
/* @var $model common\models\Localidad */
/* @var $form yii\widgets\ActiveForm */
?>

<div class="localidad-form">

    <?php $form = ActiveForm::begin(); ?>

    <!--<?= $form->field($model, 'id_localidad')->textInput() ?>-->

    <!--<?= $form->field($model, 'cod_sisa')->textInput(['maxlength' => true]) ?>-->

    <!--<?= $form->field($model, 'cod_bahra')->textInput(['maxlength' => true]) ?>-->

    <?= $form->field($model, 'nombre')->textInput(['maxlength' => true]) ?>

    <?= $form->field($model, 'cod_postal')->textInput(['maxlength' => true]) ?>

    <!--<?= $form->field($model, 'id_departamento')->textInput() ?>-->
    
    <!--<?php //aqui esta el desarrollo de la lista desplegrable del campo departamento
        $departamento = ArrayHelper::map(Departamento::find()->all(), 'id_departamento', 'nombre');
        echo $form->field($model, 'id_departamento')->dropDownList($departamento, ['prompt'=>'Por favor elija un Departamento']);
    ?>-->
    
    <!----Lista desplegables dependientes para elegir provincia y departamento----->
    <?php
    if($model->isNewRecord){// PARA PROVINCIA
        $provincia=ArrayHelper::map(Provincia::find()->asArray()->all(), 'id_provincia', 'nombre');
        echo $form->field($model, 'id_provincia')->dropDownList($provincia, 
             ['prompt'=>'-Seleccione una provincia-',
              'onchange'=>'
                $.post( "'.Yii::$app->urlManager->createUrl('localidades/lists?id=').'"+$(this).val(), function( data ) {
                  $( "select#nombre" ).html( data );
                });
            ']); 
    }else{
        $provincia = ArrayHelper::map(Provincia::find()->where(['id_provincia' => $model->idDepartamento->id_provincia])->all(), 'id_provincia', 'nombre');
        //$provincia=ArrayHelper::map(Provincia::find()->asArray()->all(), 'id_provincia', 'nombre');
        echo $form->field($model, 'id_provincia')->dropDownList($provincia,
                [
                    'options' => [$model->idDepartamento->id_provincia => ['selected ' => true]]
                ]);
    }
    
    if($model->isNewRecord){// PARA DEPARTAMENTO
    $departamento=ArrayHelper::map(Departamento::find()->asArray()->all(), 'id_departamento', 'nombre');
    echo $form->field($model, 'id_departamento')
        ->dropDownList($departamento,           
            [
             'prompt'=>'-Seleccione un departamento-',
             'id'=>'nombre'
            ]
        );
    }else{
        $departamento = ArrayHelper::map(Departamento::find()->where(['id_provincia' => $model->idDepartamento->id_provincia])->all(), 'id_departamento', 'nombre');
        echo $form->field($model, 'id_departamento')->dropDownList($departamento,
                [
                    'options' => [$model->id_departamento => ['selected ' => true]]
                ]);
    }
    ?>
    
    <!---------------------------------->
    <div class="form-group">
        <?= Html::submitButton($model->isNewRecord ? 'Guardar' : 'Guardar cambios', ['class' => $model->isNewRecord ? 'btn btn-success' : 'btn btn-primary']) ?>
        <?= Html::a('Cancelar', ['index'], ['class' => 'btn btn-success']) ?>
    </div>

    <?php ActiveForm::end(); ?>

</div>
