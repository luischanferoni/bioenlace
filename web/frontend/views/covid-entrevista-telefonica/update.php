<?php

use yii\helpers\Html;

/* @var $this yii\web\View */
/* @var $model common\models\CovidEntrevistaTelefonica */

$this->title = 'Actualizar Covid Entrevista Telefonica: ' . $model->id;
$this->params['breadcrumbs'][] = ['label' => 'Covid Entrevista Telefonicas', 'url' => ['index']];
$this->params['breadcrumbs'][] = ['label' => $model->id, 'url' => ['view', 'id' => $model->id]];
$this->params['breadcrumbs'][] = 'Actualizar';
?>
<div class="covid-entrevista-telefonica-update">

    <h1><?= Html::encode($this->title) ?></h1>

    <?= $this->render('_form', [
        'model' => $model,
        'model_persona'=> $model_persona,
        'model_investigacion_epidemiologica' => $model_investigacion_epidemiologica,
        'model_factores_riesgo' => $model_factores_riesgo,
    ]) ?>

</div>
