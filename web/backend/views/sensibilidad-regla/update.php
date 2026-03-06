<?php

use yii\helpers\Html;

/* @var $this yii\web\View */
/* @var $model common\models\SensibilidadRegla */
/* @var $servicios common\models\Servicio[] */

$this->title = 'Editar regla: ' . ($model->categoria ? $model->categoria->nombre : $model->id_categoria);
$this->params['breadcrumbs'][] = ['label' => 'Reglas de sensibilidad', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="sensibilidad-regla-update">

    <h1><?= Html::encode($this->title) ?></h1>

    <?= $this->render('_form', [
        'model' => $model,
        'servicios' => $servicios,
        'idsServicios' => array_column($model->reglaServicios, 'id_servicio'),
    ]) ?>

</div>
