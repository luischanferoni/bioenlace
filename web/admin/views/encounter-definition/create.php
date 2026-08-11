<?php

use yii\helpers\Html;

/* @var $this yii\web\View */
/* @var $model common\models\Clinical\EncounterDefinition */

$this->title = 'Nueva definición de encounter';
$this->params['breadcrumbs'][] = ['label' => 'Definiciones de encounter', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="card">
    <div class="card-header d-flex justify-content-between">
        <div class="header-title">
            <h4 class="card-title"><?= Html::encode($this->title) ?></h4>
        </div>
    </div>
    <div class="card-body">
        <?= $this->render('_form', [
            'model' => $model,
        ]) ?>
    </div>
</div>
