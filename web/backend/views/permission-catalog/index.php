<?php

use yii\helpers\Html;

/* @var $this yii\web\View */
/* @var $intents list<array<string, mixed>> */
/* @var $attributes list<array<string, mixed>> */
/* @var $flowSteps list<array<string, mixed>> */

$this->title = 'Catálogo de permisos';
$this->params['breadcrumbs'][] = ['label' => 'RBAC', 'url' => ['#']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="permission-catalog-index">

    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <h1 class="h2 mb-0"><?= Html::encode($this->title) ?></h1>
        <div>
            <?= Html::a('Integridad del catálogo', ['integrity'], ['class' => 'btn btn-primary btn-sm']) ?>
            <?= Html::a('Roles ↔ permisos', ['roles'], ['class' => 'btn btn-outline-primary btn-sm']) ?>
            <?= Html::a('Catálogo DataAccess (legacy)', ['data-access-catalog/index'], ['class' => 'btn btn-outline-secondary btn-sm']) ?>
        </div>
    </div>

    <p class="text-muted">
        Permisos declarativos: <strong>intents</strong> (operaciones / flows) y <strong>atributos</strong> (<code>data-access-config</code>).
        Los pasos <code>open_ui</code> intermedios heredan el permiso del intent padre.
    </p>

    <ul class="nav nav-tabs mb-3" role="tablist">
        <li class="nav-item">
            <a class="nav-link active" data-bs-toggle="tab" href="#tab-intents">Intents (<?= count($intents) ?>)</a>
        </li>
        <li class="nav-item">
            <a class="nav-link" data-bs-toggle="tab" href="#tab-attributes">Atributos (<?= count($attributes) ?>)</a>
        </li>
        <li class="nav-item">
            <a class="nav-link" data-bs-toggle="tab" href="#tab-flow-steps">Pasos open_ui (<?= count($flowSteps) ?>)</a>
        </li>
    </ul>

    <div class="tab-content">
        <div class="tab-pane fade show active" id="tab-intents">
            <div class="card">
                <div class="card-body p-0">
                    <table class="table table-sm table-striped mb-0">
                        <thead>
                        <tr>
                            <th>Intent</th>
                            <th>Carpeta</th>
                            <th>Permiso / ruta</th>
                            <th>Pasos UI</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($intents as $row): ?>
                            <tr>
                                <td>
                                    <code><?= Html::encode((string) ($row['intent_id'] ?? '')) ?></code>
                                    <?php if (!empty($row['action_name'])): ?>
                                        <div class="small text-muted"><?= Html::encode((string) $row['action_name']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td><?= Html::encode((string) ($row['category'] ?? '—')) ?></td>
                                <td>
                                    <?php if (!empty($row['permission'])): ?>
                                        <code><?= Html::encode((string) $row['permission']) ?></code>
                                    <?php else: ?>
                                        <code class="text-muted"><?= Html::encode((string) ($row['rbac_route'] ?? '')) ?></code>
                                    <?php endif; ?>
                                </td>
                                <td><?= (int) count($row['open_ui_steps'] ?? []) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="tab-pane fade" id="tab-attributes">
            <div class="card">
                <div class="card-body p-0">
                    <table class="table table-sm table-striped mb-0">
                        <thead>
                        <tr>
                            <th>Clave permiso</th>
                            <th>Origen</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($attributes as $row): ?>
                            <tr>
                                <td><code><?= Html::encode((string) ($row['key'] ?? '')) ?></code></td>
                                <td class="small text-muted"><?= Html::encode((string) ($row['source'] ?? '')) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="tab-pane fade" id="tab-flow-steps">
            <div class="card">
                <div class="card-body p-0">
                    <table class="table table-sm table-striped mb-0">
                        <thead>
                        <tr>
                            <th>Intent padre</th>
                            <th>Paso</th>
                            <th>ui_action</th>
                            <th>Hereda permiso</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($flowSteps as $row): ?>
                            <tr>
                                <td><code><?= Html::encode((string) ($row['intent_id'] ?? '')) ?></code></td>
                                <td><code><?= Html::encode((string) ($row['step_id'] ?? '')) ?></code></td>
                                <td><code><?= Html::encode((string) ($row['action_id'] ?? '')) ?></code></td>
                                <td class="small"><code><?= Html::encode((string) ($row['inherits_permission'] ?? '')) ?></code></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
