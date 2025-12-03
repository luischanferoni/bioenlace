<?php

use yii\helpers\Html;

/* @var $this yii\web\View */
/* @var $model common\models\PersonaPrograma */

$this->title = 'Update Persona Programa: ' . $model->id;
$this->params['breadcrumbs'][] = ['label' => 'Persona Programas', 'url' => ['index']];
$this->params['breadcrumbs'][] = ['label' => $model->id, 'url' => ['view', 'id' => $model->id]];
$this->params['breadcrumbs'][] = 'Update';
?>
<div class="persona-programa-update">

    <h1><?= Html::encode($this->title) ?></h1>

    <?= $this->render('_form', [
        'model' => $model,
    ]) ?>

</div>
