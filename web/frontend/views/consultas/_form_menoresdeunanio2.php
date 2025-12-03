<?php

use yii\helpers\Html;
use yii\widgets\ActiveForm;
use wbraganca\dynamicform\DynamicFormWidget;
use yii\jui\JuiAsset;
use common\models\Cie10;
use kartik\select2\Select2;
use nex\chosen\Chosen;
use yii2mod\chosen\ChosenSelect;
use yii\helpers\ArrayHelper; 
//use nex\chosen\Chosen;

/* @var $this yii\web\View */
/* @var $model common\models\Consulta */
/* @var $form yii\widgets\ActiveForm */

?>

<div class="row">    
    
    <div style="width: 30%; float: left; margin-right: 5%">
    <?= $form->field($model, 'id_tipo_ingreso')->
        dropDownList(\common\models\TipoIngreso::getListaTipoIngresoPorConsulta(1),
        ['prompt' => '-Elija una opción-']); ?>    
        </div>
</div>  
<div class="row">    
    
    <div style="width: 15%; float: left; margin-right: 5%">
        <?= $form->field($model_valoracion_nutricional, 'peso')->textInput() ?>    
    </div>
    <div style="width: 15%; float: left; margin-right: 5%">
        <?= $form->field($model_valoracion_nutricional, 'talla')->textInput() ?>    
    </div>
    <div style="width: 15%; float: left; margin-right: 5%">
        <?= $form->field($model_valoracion_nutricional, 'perim_cefalico')->textInput() ?>    
    </div>
    <div style="width: 20%; float: left; margin-right: 5%">
        <?= $form->field($model_valoracion_nutricional, 'per_perim_cefalico')
                    ->dropDownList([ '3' => '3', '10' => '10','25' => '25','50' => '50','75' => '75','90' => '90','97' => '97']
                                , ['prompt' => '-Seleccione un valor-']) ?>    
    </div>
</div>
