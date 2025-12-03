<?php

use yii\helpers\Html;

/* @var $this yii\web\View */
/* @var $model common\models\ServiciosEfector */

$this->title = 'Modificar el servicio '.$model->servicio->nombre.' para este efector';
$this->params['breadcrumbs'][] = ['label' => 'Servicios por Efector', 'url' => ['index']];
$this->params['breadcrumbs'][] = ['label' => $model->id_servicio, 'url' => ['view', 'id_servicio' => $model->id_servicio, 'id_efector' => $model->id_efector,'horario' => $model->horario]];
$this->params['breadcrumbs'][] = 'Modificar';
?>

<?= $this->render('_form', [
    'model' => $model,
]) ?>

