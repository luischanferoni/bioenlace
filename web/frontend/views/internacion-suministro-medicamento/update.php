<?php

use yii\helpers\Html;

/* @var $this yii\web\View */
/* @var $model common\models\InfraestructuraSala */

$this->title = 'Actualizar suministro de medicamento: ' . $model->id;
$this->params['breadcrumbs'][] = ['label' => 'Suministro de medicamento', 'url' => ['index']];
$this->params['breadcrumbs'][] = ['label' => $model->id, 'url' => ['view', 'id' => $model->id]];
$this->params['breadcrumbs'][] = 'Actualizar';
?>
<div class="internacion-suministro-medicamento-update">

    <h1><?= Html::encode($this->title) ?></h1>

    <?= $this->render('_form', [
        'model' => $model,
        'medicamentosInternacion' => $medicamentosInternacion
    ]) ?>

</div>
