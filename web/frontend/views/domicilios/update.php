<?php

use yii\helpers\Html;

/* @var $this yii\web\View */
/* @var $model common\models\domicilio */

//$this->title = 'Actualizar Domicilio: ' . ' ' . $model->id_domicilio;
$this->title = 'Actualizar Domicilio: ' . ' ' . $model_persona->nombre. ' ' . $model_persona->apellido;
$this->params['breadcrumbs'][] = ['label' => 'Domicilios', 'url' => ['index']];
$this->params['breadcrumbs'][] = ['label' => $model->id_domicilio, 'url' => ['view', 'id' => $model->id_domicilio]];
$this->params['breadcrumbs'][] = 'Actualizar';
?>
<div class="domicilio-update">

    <h1><?= Html::encode($this->title) ?></h1>

    <?= $this->render('_form', [
        'model' => $model,
        'model_persona'=> $model_persona,
        'model_persona_domicilio'=> $model_persona_domicilio,
        'model_localidad'=> $model_localidad,
        'model_departamento'=> $model_departamento,
        'model_provincia'=> $model_provincia,
    ]) ?>

</div>
