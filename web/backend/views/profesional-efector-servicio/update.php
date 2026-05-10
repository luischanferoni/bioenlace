<?php

use yii\helpers\Html;

/* @var $this yii\web\View */
/* @var $model common\models\ProfesionalEfectorServicio */

$this->title = 'Actualizar PES #' . (int) $model->id;
$this->params['breadcrumbs'][] = ['label' => 'Profesional–efector–servicio', 'url' => ['index']];
$this->params['breadcrumbs'][] = ['label' => 'PES #' . (int) $model->id, 'url' => ['view', 'id' => $model->id, 'id_efector' => $model->id_efector]];
$this->params['breadcrumbs'][] = 'Update';
?>
<div class="profesional-efector-servicio-update">

    <h1><?= Html::encode($this->title) ?></h1>

    <?= $this->render('_form', [
        'model' => $model,
    ]) ?>

</div>
