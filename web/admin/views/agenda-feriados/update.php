<?php

use yii\helpers\Html;

/* @var $this yii\web\View */
/* @var $model common\models\AgendaFeriados */

$this->title = 'Update Agenda Feriados: ' . $model->id;
$this->params['breadcrumbs'][] = ['label' => 'Agenda Feriados', 'url' => ['index']];
$this->params['breadcrumbs'][] = ['label' => $model->id, 'url' => ['view', 'id' => $model->id]];
$this->params['breadcrumbs'][] = 'Update';
?>
<div class="agenda-feriados-update">

    <h1><?= Html::encode($this->title) ?></h1>

    <?= $this->render('_form', [
        'model' => $model,
    ]) ?>

</div>
