<?php

use yii\helpers\Html;

/* @var $this yii\web\View */
/* @var $intents list<array<string, mixed>> */
/* @var $flowSteps list<array<string, mixed>> */
/* @var $rolesByKey array<string, list<string>> */
/* @var $intentInAuth array<string, bool> */
/* @var $roleNames list<string> */
/* @var $unregisteredIntentsCount int */

$this->title = 'Catálogo de permisos';
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="permission-catalog-index">

    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
            <h1 class="h2 mb-0"><?= Html::encode($this->title) ?></h1>
            <p class="text-muted small mb-0 mt-1">
                Asigná <strong>roles ↔ intents</strong>. Los campos visibles/editables se definen en el YAML de cada intent.
            </p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <?= Html::a('Roles RBAC', ['/user-management/role/index'], ['class' => 'btn btn-outline-primary btn-sm']) ?>
            <?= Html::beginForm(['sync'], 'post', ['class' => 'd-inline']) ?>
                <?= Html::submitButton('Sincronizar intents → auth_item', [
                    'class' => 'btn btn-warning btn-sm',
                    'data' => ['confirm' => '¿Registrar intents en auth_item?'],
                ]) ?>
            <?= Html::endForm() ?>
            <?= Html::a('Integridad', ['integrity'], ['class' => 'btn btn-primary btn-sm']) ?>
        </div>
    </div>

    <?php if ($unregisteredIntentsCount > 0): ?>
        <div class="alert alert-warning py-2 small">
            <?= (int) $unregisteredIntentsCount ?> intent(s) aún no están en <code>auth_item</code>.
            Ejecutá «Sincronizar intents → auth_item».
        </div>
    <?php endif; ?>

    <ul class="nav nav-tabs mb-3" role="tablist">
        <li class="nav-item">
            <a class="nav-link active" data-bs-toggle="tab" href="#tab-intents">Intents (<?= count($intents) ?>)</a>
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
                            <th>Operación</th>
                            <th>Familia</th>
                            <th>Ruta API</th>
                            <th>Roles</th>
                            <th class="text-end">Acciones</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($intents as $row): ?>
                            <?php
                            $key = (string) ($row['key'] ?? $row['intent_id'] ?? '');
                            if ($key === '' || strncmp($key, '/api/', 5) === 0) {
                                continue;
                            }
                            $intentId = (string) ($row['intent_id'] ?? '');
                            $roles = $rolesByKey[$key] ?? [];
                            $inAuth = $intentInAuth[$key] ?? false;
                            ?>
                            <tr class="<?= !$inAuth ? 'table-warning' : '' ?>">
                                <td>
                                    <code><?= Html::encode($intentId) ?></code>
                                    <?php if (!empty($row['action_name'])): ?>
                                        <div class="small text-muted"><?= Html::encode((string) $row['action_name']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="small"><?= Html::encode((string) ($row['operation'] ?? '—')) ?></td>
                                <td class="small">
                                    <?php if (!empty($row['intent_family'])): ?>
                                        <code><?= Html::encode((string) $row['intent_family']) ?></code>
                                    <?php else: ?>
                                        —
                                    <?php endif; ?>
                                </td>
                                <td><code class="small"><?= Html::encode((string) ($row['rbac_route'] ?? '—')) ?></code></td>
                                <td class="small">
                                    <?= $roles === [] ? '—' : Html::encode(implode(', ', $roles)) ?>
                                </td>
                                <td class="text-end text-nowrap">
                                    <?= Html::a('Detalle', ['view-intent', 'intent_id' => $intentId], [
                                        'class' => 'btn btn-outline-secondary btn-sm',
                                    ]) ?>
                                    <?= Html::a('Roles', ['edit-intent-roles', 'key' => $key], [
                                        'class' => 'btn btn-outline-primary btn-sm',
                                    ]) ?>
                                </td>
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
