<?php

use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var array<string, mixed> $model */
/** @var array<int|string, string> $servicios */
/** @var list<string> $placeholders */

$this->title = 'Nueva plantilla de epicrisis';
$this->params['breadcrumbs'][] = ['label' => 'Plantillas de epicrisis', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>

<div class="card">
    <div class="card-body">
        <h1 class="h4 mb-3"><?= Html::encode($this->title) ?></h1>
        <?php if (Yii::$app->session->hasFlash('error')): ?>
            <div class="alert alert-danger"><?= Html::encode((string) Yii::$app->session->getFlash('error')) ?></div>
        <?php endif; ?>
        <?= $this->render('_form', [
            'model' => $model,
            'servicios' => $servicios,
            'placeholders' => $placeholders,
            'showActivo' => false,
            'submitLabel' => 'Crear plantilla',
        ]) ?>
    </div>
</div>
