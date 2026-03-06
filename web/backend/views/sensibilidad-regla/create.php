<?php

use yii\helpers\Html;

/* @var $this yii\web\View */
/* @var $model common\models\SensibilidadRegla */
/* @var $categoria common\models\SensibilidadCategoria */
/* @var $servicios common\models\Servicio[] */

$this->title = 'Nueva regla: ' . $categoria->nombre;
$this->params['breadcrumbs'][] = ['label' => 'Reglas de sensibilidad', 'url' => ['index']];
$this->params['breadcrumbs'][] = ['label' => 'Categorías', 'url' => ['sensibilidad-categoria/index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="sensibilidad-regla-create">

    <h1><?= Html::encode($this->title) ?></h1>

    <?= $this->render('_form', [
        'model' => $model,
        'servicios' => $servicios,
        'idsServicios' => [],
    ]) ?>

</div>
