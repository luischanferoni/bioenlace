<?php

use yii\helpers\Html;
use yii\widgets\ActiveForm;
use common\models\Efector;
use webvimark\modules\UserManagement\models\User;
use yii\helpers\ArrayHelper;
use kartik\select2\Select2;
/* @var $this yii\web\View */
/* @var $model common\models\UserEfector */
/* @var $form yii\widgets\ActiveForm */
?>

<div class="user-efector-form">

    <?php $form = ActiveForm::begin(); ?>

   
    <?=
        Html::activeLabel($model, 'id_user', [
            'label' => 'Usuario: ',
            'class' => 'col-sm-2 control-label'
        ])
        ?>
    <div class="col-sm-4">
            <?=
            $form->field($model, 'id_user', [
                'template' => '{input}{error}{hint}'
            ])->widget(Select2::classname(), [
                'data' => ArrayHelper::map(User::find()->all(), 'id','username'),
                'theme'=>'bootstrap',  
                'language' => 'es',
                'options' => ['placeholder' => 'Seleccione un usuario' ],
                'pluginOptions' => [
                    'allowClear' => true
                    ],
                ]);
            ?>
        </div>
    
    <?=
        Html::activeLabel($model, 'id_efector', [
            'label' => 'Efector: ',
            'class' => 'col-sm-2 control-label'
        ])
        ?>
    <div class="col-sm-4">
            <?=
            $form->field($model, 'id_efector', [
                'template' => '{input}{error}{hint}'
            ])->widget(Select2::classname(), [
                'data' => ArrayHelper::map(Efector::find()->all(), 'id_efector','nombre'),
                'theme'=>'bootstrap',  
                'language' => 'es',
                'options' => ['placeholder' => 'Seleccione un efector' ],
                'pluginOptions' => [
                    'allowClear' => true
                    ],
                ]);
            ?>
        </div>

    <div class="form-group">
        <?= Html::submitButton($model->isNewRecord ? 'Guardar' : 'Actualizar', ['class' => $model->isNewRecord ? 'btn btn-success' : 'btn btn-primary']) ?>
    </div>

    <?php ActiveForm::end(); ?>

</div>
