<?php

use yii\helpers\Html;
use yii\widgets\ActiveForm;
//use nex\chosen\Chosen;
use kartik\select2\Select2;
use yii\helpers\ArrayHelper;
//use common\models\Mensajes; 
use \webvimark\modules\UserManagement\models\User;
//use common\models\User;


/* @var $this yii\web\View */
/* @var $model common\models\Mensajes */
/* @var $form yii\widgets\ActiveForm */


 
?>
<div class="enviados-form">
  
    
    <?php $form = ActiveForm::begin();  ?> 
  
  
  
  <?=
    $form->field($model, 'id_receptor')->widget(Select2::classname(), [
    
    'data' => ArrayHelper::map(User::find()->all(),'id' ,'username'), 
    'theme'=>'bootstrap',  
    'language' => 'es',
    'options' => ['placeholder' => 'Seleccione el receptor del mensaje'],
    'pluginOptions' => [
        'allowClear' => true
    ],
    ]);
   
   ?>
   
    <?= $form->field($model, 'asunto')->textarea(['rows' => 1]);?>
    <?= $form->field($model, 'texto')->textarea(['rows' => 6]);?>

    <div class="form-group">
        <?= Html::submitButton($model->isNewRecord ? 'Enviar' : 'Actualizar', ['class' => $model->isNewRecord ? 'btn btn-success' : 'btn btn-primary']) ?>
    </div>

    <?php ActiveForm::end(); ?>

</div>
