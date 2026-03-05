<?php

use yii\helpers\Html;

/* @var $this yii\web\View */
/* @var $model common\models\SensibilidadCategoria */

$this->title = 'Editar categoría: ' . $model->nombre;
$this->params['breadcrumbs'][] = ['label' => 'Categorías de sensibilidad', 'url' => ['index']];
$this->params['breadcrumbs'][] = ['label' => $model->nombre, 'url' => ['view', 'id' => $model->id]];
$this->params['breadcrumbs'][] = 'Editar';
?>
<div class="sensibilidad-categoria-update">

    <h1><?= Html::encode($this->title) ?></h1>

    <?= $this->render('_form', [
        'model' => $model,
    ]) ?>

</div>
