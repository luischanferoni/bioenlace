<?php

use yii\helpers\Html;

/* @var $this yii\web\View */
/* @var $model common\models\CovidEntrevistaTelefonica */

$this->title = 'Triaje Telefónico para condición Post Covid-19';
$this->params['breadcrumbs'][] = ['label' => 'Covid Entrevistas Telefonicas', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="covid-entrevista-telefonica-create">

    <h1><?= Html::encode($this->title) ?></h1>

    <?= $this->render('_form', [
        'model' => $model,
        'model_persona'=> $model_persona,
        'model_investigacion_epidemiologica' => $model_investigacion_epidemiologica,
        'model_factores_riesgo' => $model_factores_riesgo,
    ]) ?>

</div>
