<?php

use yii\helpers\Html;

/** @var yii\web\View $this */

$this->title = 'Internación — operaciones';
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="card">
    <div class="card-body">
        <h1 class="h4"><?= Html::encode($this->title) ?></h1>
        <p class="text-muted">
            El mapa de camas y el listado de internados están en el
            <strong>inicio del médico</strong> con contexto de internación (IMP) en sesión.
        </p>
        <div class="d-flex flex-wrap gap-2 mt-3">
            <?= Html::a('Ir al inicio (pacientes)', ['/site/pacientes'], ['class' => 'btn btn-primary']) ?>
            <?= Html::a('Ronda de pacientes', ['/internacion/ronda'], ['class' => 'btn btn-outline-primary']) ?>
            <?= Html::a('Plantillas de epicrisis', ['/internacion-epicrisis-plantilla/index'], ['class' => 'btn btn-outline-secondary']) ?>
        </div>
    </div>
</div>
