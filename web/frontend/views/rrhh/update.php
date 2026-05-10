<?php

use yii\helpers\Html;

/* @var $this yii\web\View */
/* @var $model common\models\ProfesionalEfectorServicio */
/* @var $model_persona common\models\Persona|null */

$this->title = 'Actualizar asignación PES: ' . $model->id;
$this->params['breadcrumbs'][] = ['label' => 'RRHH', 'url' => ['index']];
$this->params['breadcrumbs'][] = ['label' => (string) $model->id, 'url' => ['view', 'id' => $model->id]];
$this->params['breadcrumbs'][] = 'Actualizar';
?>
<div class="rrhh-update">

    <h1><?= Html::encode($this->title) ?></h1>

    <?= $this->render('_form', [
        'model' => $model,
        'model_persona' => $model_persona,
    ]) ?>

</div>
