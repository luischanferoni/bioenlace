<?php

use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var array<string, mixed> $plantilla */
/** @var array<int|string, string> $servicios */
/** @var list<string> $placeholders */

$this->title = 'Editar plantilla';
$this->params['breadcrumbs'][] = ['label' => 'Plantillas de epicrisis', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>

<div class="card">
    <div class="card-body">
        <h1 class="h4 mb-1"><?= Html::encode($this->title) ?></h1>
        <p class="text-muted small"><?= Html::encode((string) ($plantilla['nombre'] ?? '')) ?></p>
        <?php if (Yii::$app->session->hasFlash('error')): ?>
            <div class="alert alert-danger"><?= Html::encode((string) Yii::$app->session->getFlash('error')) ?></div>
        <?php endif; ?>
        <?= $this->render('_form', [
            'model' => $plantilla,
            'servicios' => $servicios,
            'placeholders' => $placeholders,
            'showActivo' => true,
            'submitLabel' => 'Guardar cambios',
        ]) ?>
    </div>
</div>
