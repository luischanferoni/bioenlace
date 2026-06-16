<?php

use yii\helpers\Html;

/* @var $this yii\web\View */
/* @var $result array{errors: list<string>, warnings: list<string>, summary: array<string, int>} */

$this->title = 'Integridad del catálogo de permisos';
$this->params['breadcrumbs'][] = ['label' => 'Catálogo de permisos', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;

$errors = $result['errors'] ?? [];
$warnings = $result['warnings'] ?? [];
$summary = $result['summary'] ?? [];
$ok = $errors === [];
?>
<div class="permission-catalog-integrity">

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h2 mb-0"><?= Html::encode($this->title) ?></h1>
        <?= Html::a('Volver al catálogo', ['index'], ['class' => 'btn btn-outline-secondary btn-sm']) ?>
    </div>

    <div class="alert <?= $ok ? 'alert-success' : 'alert-danger' ?> mb-4">
        <?php if ($ok): ?>
            <strong>OK</strong> — sin errores bloqueantes.
        <?php else: ?>
            <strong><?= count($errors) ?> error(es)</strong> — revisar antes de desplegar cambios al catálogo.
        <?php endif; ?>
        <?php if ($warnings !== []): ?>
            <span class="ms-2"><?= count($warnings) ?> advertencia(s).</span>
        <?php endif; ?>
    </div>

    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <div class="display-6"><?= (int) ($summary['intents'] ?? 0) ?></div>
                    <div class="text-muted small">Intents</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <div class="display-6"><?= (int) ($summary['extended_intents'] ?? 0) ?></div>
                    <div class="text-muted small">Intents con contrato extendido</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <div class="display-6"><?= (int) ($summary['attributes'] ?? 0) ?></div>
                    <div class="text-muted small">Atributos legacy (integridad)</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <div class="display-6"><?= (int) ($summary['flow_steps'] ?? 0) ?></div>
                    <div class="text-muted small">Pasos open_ui</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <div class="display-6"><?= (int) ($summary['errors'] ?? 0) ?></div>
                    <div class="text-muted small">Errores</div>
                </div>
            </div>
        </div>
    </div>

    <p class="text-muted small">
        CLI: <code>php yii catalog-integrity/check</code> — incluye validación DataAccess existente.
    </p>

    <?php if ($errors !== []): ?>
        <div class="card mb-4 border-danger">
            <div class="card-header text-danger"><strong>Errores</strong></div>
            <ul class="list-group list-group-flush">
                <?php foreach ($errors as $err): ?>
                    <li class="list-group-item small"><?= Html::encode($err) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if ($warnings !== []): ?>
        <div class="card mb-4 border-warning">
            <div class="card-header text-warning"><strong>Advertencias</strong></div>
            <ul class="list-group list-group-flush">
                <?php foreach ($warnings as $warn): ?>
                    <li class="list-group-item small"><?= Html::encode($warn) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?= Html::a('Ejecutar de nuevo', ['integrity'], ['class' => 'btn btn-primary btn-sm']) ?>
</div>
