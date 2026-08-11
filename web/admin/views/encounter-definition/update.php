<?php

use yii\helpers\Html;

/* @var $this yii\web\View */
/* @var $model common\models\Clinical\EncounterDefinition */

$this->title = 'Editar definición de encounter: ' . $model->id;
$this->params['breadcrumbs'][] = ['label' => 'Definiciones de encounter', 'url' => ['index']];
$this->params['breadcrumbs'][] = ['label' => (string) $model->id, 'url' => ['view', 'id' => $model->id]];
$this->params['breadcrumbs'][] = 'Editar';
?>
<div class="card">
    <div class="card-header">
        <h4 class="card-title"><?= Html::encode($this->title) ?></h4>
    </div>
    <div class="card-body">
        <?= $this->render('_form', [
            'model' => $model,
        ]) ?>
    </div>
</div>
