<?php

use yii\helpers\Html;
use yii\helpers\Url;
use yii\helpers\ArrayHelper;
use yii\widgets\ActiveForm;
use yii\web\JsExpression;

use kartik\select2\Select2;
use kartik\date\DatePicker;
use wbraganca\dynamicform\DynamicFormWidget;

use common\models\Efector;
use common\models\Servicio;
use common\models\Condiciones_laborales;

/* @var $this yii\web\View */
/* @var $model common\models\RrhhEfector */
/* @var $form yii\widgets\ActiveForm */
?>

<div class="rrhh-efector-form">

    <?php $form = ActiveForm::begin(); ?>
    <?php 
        if ($error) {
            echo "<div class='error-summary'><ul>";
            foreach($error as $e) {
                echo "<li>".$e."</li>";
            }
            echo "</ul></div>";
        }
    ?>
    <div class="form-group">
        <?php
            echo Select2::widget([
                'name' => 'efectores',
                'value' => $persona_efectores,
                'data' => ArrayHelper::map(Efector::find()->all(), 'id_efector','nombre'),
                'theme' => 'bootstrap',
                'options' => ['multiple' => true, 'placeholder' => 'Seleccione Efector', 'id' => 'efectores'],
                'pluginOptions' => [
                    'allowClear' => true
                ],
            ]);
        /* echo $form->field($model, 'id_efector', ['template' => '{input}{error}{hint}'])
                    ->widget(Select2::classname(), [
                        'data' => ArrayHelper::map(Efector::find()->all(), 'id_efector','nombre'),
                        'theme'=>'bootstrap',  
                        'language' => 'es',
                        'options' => ['placeholder' => 'Seleccione un efector', 'multiple' => true],
                        'pluginOptions' => [
                            'allowClear' => true
                            ],
                        ]);*/
        ?>
    </div>

    <div class="form-group pull-right">
        <?= Html::submitButton('Guardar', ['class' => 'btn btn-success']) ?>
    </div>

    <?php ActiveForm::end(); ?>    

</div>

