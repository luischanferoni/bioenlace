<?php

use yii\helpers\Html;
use yii\widgets\ActiveForm;
use kartik\date\DatePicker;
use common\models\Cie10;

use kartik\select2\Select2;
use yii\helpers\ArrayHelper; 
//use nex\chosen\Chosen;
use yii\helpers\Url;
use yii\bootstrap5\Modal;

/* @var $this yii\web\View */
/* @var $model common\models\Consulta */
/* @var $form yii\widgets\ActiveForm */
?>

<div class="form-inline">

    <div class="row" style="padding: 1%">
        <div class="col-xs-3"><?= $form->field($model_embarazo, 'metodo_anticonceptivo')->checkbox()?></div>
        <div class="col-xs-4">
            <?=
            $form->field($model_embarazo, 'fecha_parto')
                    ->widget(DatePicker::className(), [
                        'name' => 'check_issue_date',
                        'value' => date('Y-M-d', strtotime('+2 days')),
                        'options' => ['placeholder' => '-Seleccione una fecha-'],
                        'pluginOptions' => [
                            'format' => 'yyyy-mm-dd',
                            'autoclose' => true,
                        ]
            ])->label('Fecha de Parto');
            ?>
        </div>  
    </div>

</div>