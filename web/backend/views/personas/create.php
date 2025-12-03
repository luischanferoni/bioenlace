<?php

use yii\helpers\Html;


/* @var $this yii\web\View */
/* @var $model common\models\persona */

$this->title = 'Alta de Persona';
$this->params['breadcrumbs'][] = ['label' => 'Personas', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="persona-create">
<?php    print_r($mensaje); 
// var_dump($valid);
//print_r($datospersona);
?>
    <h1><?= Html::encode($this->title) ?></h1>

    <?= $this->render('_form', [
        'model' => $model, 
        'model_persona_telefono' => $model_persona_telefono,
        'model_tipo_telefono' => $model_tipo_telefono,
        'model_domicilio' => $model_domicilio,
        'model_persona_domicilio' => $model_persona_domicilio,
        'model_localidad' => $model_localidad,
        'model_provincia' => $model_provincia, 
        'model_departamento' => $model_departamento, 
        'model_persona_mails' => $model_persona_mails,        
        
    ]) ?>

</div>
