<?php

use yii\helpers\Html;

/* @var $this yii\web\View */
/* @var $model common\models\SensibilidadMapeoSnomed */
/* @var $categorias common\models\SensibilidadCategoria[] */

$this->title = 'Editar mapeo: ' . $model->codigo;
$this->params['breadcrumbs'][] = ['label' => 'Mapeo SNOMED', 'url' => ['index']];
$this->params['breadcrumbs'][] = ['label' => $model->codigo, 'url' => ['view', 'id' => $model->id]];
$this->params['breadcrumbs'][] = 'Editar';
?>
<div class="sensibilidad-mapeo-update">

    <h1><?= Html::encode($this->title) ?></h1>

    <?= $this->render('_form', [
        'model' => $model,
        'categorias' => $categorias,
    ]) ?>

</div>
