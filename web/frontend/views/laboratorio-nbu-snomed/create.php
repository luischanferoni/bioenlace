<?php

use yii\helpers\Html;

/* @var $this yii\web\View */
/* @var $model common\models\LaboratorioNbuSnomed */

$this->title = 'Cargar equivalencias Nbu-Snomed';
$this->params['breadcrumbs'][] = ['label' => 'Listado de Equivalencias', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="laboratorio-nbu-snomed-create">

    <h1><?= Html::encode($this->title) ?></h1>

    <?= $this->render('_form', [
        'model' => $model,
        'dataProvider' => $dataProvider
    ]) ?>

</div>
