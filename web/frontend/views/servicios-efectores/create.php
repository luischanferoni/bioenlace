<?php

use yii\helpers\Html;


/* @var $this yii\web\View */
/* @var $model common\models\ServiciosEfector */

$this->title = 'Nuevo Servicio Efector';
$this->params['breadcrumbs'][] = ['label' => 'Servicios por Efector', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;

?>

<?= $this->render('_form', [
    'model' => $model,
]) ?>

