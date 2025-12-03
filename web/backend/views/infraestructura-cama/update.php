<?php

use yii\helpers\Html;

/* @var $this yii\web\View */
/* @var $model common\models\InfraestructuraCama */

$this->title = 'Actualizar Cama: ' . $model->id;
$this->params['breadcrumbs'][] = ['label' => 'Camas', 'url' => ['index']];
$this->params['breadcrumbs'][] = ['label' => $model->id, 'url' => ['view', 'id' => $model->id]];
$this->params['breadcrumbs'][] = 'Actualizar';
?>
<div class="infraestructura-cama-update">

    <h1><?= Html::encode($this->title) ?></h1>

    <?= $this->render('_form', [
        'model' => $model,
    ]) ?>

</div>
