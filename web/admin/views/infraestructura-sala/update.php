<?php

use yii\helpers\Html;

/* @var $this yii\web\View */
/* @var $model common\models\InfraestructuraSala */

$this->title = 'Actualizar Sala N°: ' . $model->nro_sala;
$this->params['breadcrumbs'][] = ['label' => 'Salas', 'url' => ['index']];
$this->params['breadcrumbs'][] = ['label' => $model->id, 'url' => ['view', 'id' => $model->id]];
$this->params['breadcrumbs'][] = 'Actualizar';
?>
<div class="infraestructura-sala-update">

    <h1><?= Html::encode($this->title) ?></h1>

    <?= $this->render('_form', [
        'model' => $model,
    ]) ?>

</div>
