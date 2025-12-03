<?php

use yii\helpers\Html;
use yii\widgets\ActiveForm;

/* @var $this yii\web\View */
/* @var $model common\models\Especialidades */
/* @var $form yii\widgets\ActiveForm */
$profesiones = \common\models\Profesiones::find()->indexBy('id_profesion')->asArray()->all();
$lista_profesiones = \yii\helpers\ArrayHelper::map($profesiones, 'id_profesion', 'nombre');
?>

<div class="especialidades-form">


    <?php $form = ActiveForm::begin();  ?> 
<?= $form->field($model, 'id_profesion')->dropDownList($lista_profesiones, ['prompt'=>'Elija una opción...']);?>
    <a href="_form.php"></a>
 
    <?=$form->field($model, 'nombre')->textInput(['maxlength' => true])  ?>
    
     
    


    <div class="form-group">
        <?= Html::submitButton($model->isNewRecord ? 'Nuevo' : 'Actualizar', ['class' => $model->isNewRecord ? 'btn btn-success' : 'btn btn-primary']) ?>
    </div>

    <?php ActiveForm::end(); ?>

</div>

