<?php

use yii\helpers\Html;

/* @var $this yii\web\View */
/* @var $model common\models\Servicio */

$this->title = 'Actualizar Servicio: ' . ' ' . $model->id_servicio . ' - ' .  $model->nombre;
$this->params['breadcrumbs'][] = ['label' => 'Servicios', 'url' => ['index']];
$this->params['breadcrumbs'][] = ['label' => $model->id_servicio . ' - ' .  $model->nombre, 'url' => ['view', 'id' => $model->id_servicio]];
$this->params['breadcrumbs'][] = 'Actualizar';
?>
<div class="servicio-update">

    <h1><?= Html::encode($this->title) ?></h1>

    <?= $this->render('_form', [
        'model' => $model,
    ]) ?>

</div>
