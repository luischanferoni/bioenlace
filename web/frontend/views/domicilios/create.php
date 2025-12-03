<?php

use yii\helpers\Html;


/* @var $this yii\web\View */
/* @var $model common\models\domicilio */

$this->title = 'Nuevo Domicilio';
$this->params['breadcrumbs'][] = ['label' => 'Domicilios', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="domicilio-create">

    <?= $this->render('_form', [
        'model' => $model,
        'model_persona'=> $model_persona,
        'model_persona_domicilio'=> $model_persona_domicilio,
        'model_localidad'=> $model_localidad,
        'model_departamento'=> $model_departamento,
        'model_provincia'=> $model_provincia,
    ]) ?>
</div>
