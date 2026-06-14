<?php

use yii\helpers\Html;

/* @var $this yii\web\View */
/* @var $intents list<array<string, mixed>> */
/* @var $attributes list<array<string, mixed>> */
/* @var $flowSteps list<array<string, mixed>> */
/* @var $rolesByKey array<string, list<string>> */
/* @var $inAuthItemByKey array<string, bool> */
/* @var $roleNames list<string> */
/* @var $unregisteredCount int */
/* @var $unassignedCount int */

$this->title = 'Catálogo de permisos';
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="permission-catalog-index">

    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <h1 class="h2 mb-0"><?= Html::encode($this->title) ?></h1>
        <div class="d-flex flex-wrap gap-2">
            <?= Html::beginForm(['sync'], 'post', ['class' => 'd-inline']) ?>
                <?= Html::submitButton('Sincronizar → auth_item', [
                    'class' => 'btn btn-warning btn-sm',
                    'data' => ['confirm' => '¿Registrar intents/atributos en auth_item?'],
                ]) ?>
            <?= Html::endForm() ?>
            <?= Html::a('Integridad', ['integrity'], ['class' => 'btn btn-primary btn-sm']) ?>
        </div>
    </div>

    <?php if ($unregisteredCount > 0): ?>
        <div class="alert alert-warning py-2 small">
            <?= (int) $unregisteredCount ?> permiso(s) del catálogo aún no están en <code>auth_item</code>.
            Ejecutá «Sincronizar → auth_item».
        </div>
    <?php endif; ?>
    <?php if ($unassignedCount > 0): ?>
        <div class="alert alert-secondary py-2 small">
            <?= (int) $unassignedCount ?> permiso(s) sin ningún rol asignado.
            Editá un rol abajo para asignar grants.
        </div>
    <?php endif; ?>

    <p class="text-muted">
        Permisos declarativos: <strong>intents</strong> (clave = <code>intent_id</code>) y <strong>atributos</strong>
        (<code>Entidad.atributo.read|info|edit</code> en <code>data-access-config</code>).
        Los grupos YAML son solo presentación; la asignación es por intent o atributo.
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
                            <th>Intent (permiso)</th>
                            <th>Carpeta</th>
                            <th>Ruta API</th>
                            <th>Roles con acceso</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($intents as $row): ?>
                            <?php
                            $key = (string) ($row['key'] ?? $row['intent_id'] ?? '');
                            $roles = $rolesByKey[$key] ?? [];
                            $inAuth = $inAuthItemByKey[$key] ?? false;
                            ?>
                            <tr class="<?= !$inAuth ? 'table-warning' : '' ?>">
                                <td>
                                    <code><?= Html::encode((string) ($row['intent_id'] ?? '')) ?></code>
                                    <?php if (!empty($row['action_name'])): ?>
                                        <div class="small text-muted"><?= Html::encode((string) $row['action_name']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td><?= Html::encode((string) ($row['category'] ?? '—')) ?></td>
                                <td><code class="small"><?= Html::encode((string) ($row['rbac_route'] ?? '—')) ?></code></td>
                                <td class="small">
                                    <?php if ($roles === []): ?>
                                        <span class="text-muted">—</span>
                                    <?php else: ?>
                                        <?= Html::encode(implode(', ', $roles)) ?>
                                    <?php endif; ?>
                                </td>
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
                            <th>Roles con acceso</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($attributes as $row): ?>
                            <?php
                            $key = (string) ($row['key'] ?? '');
                            $roles = $rolesByKey[$key] ?? [];
                            $inAuth = $inAuthItemByKey[$key] ?? false;
                            ?>
                            <tr class="<?= !$inAuth ? 'table-warning' : '' ?>">
                                <td><code><?= Html::encode($key) ?></code></td>
                                <td class="small text-muted"><?= Html::encode((string) ($row['source'] ?? '')) ?></td>
                                <td class="small">
                                    <?= $roles === [] ? '—' : Html::encode(implode(', ', $roles)) ?>
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

    <?php if ($roleNames !== []): ?>
        <div class="card mt-4">
            <div class="card-header py-2">
                <strong class="small">Asignar permisos por rol</strong>
            </div>
            <div class="card-body py-2">
                <p class="text-muted small mb-2">
                    Elegí un rol para marcar qué intents y atributos tiene asignados en <code>auth_item</code>.
                </p>
                <div class="d-flex flex-wrap gap-2">
                    <?php foreach ($roleNames as $rn): ?>
                        <?= Html::a(Html::encode($rn), ['edit-role', 'role' => $rn], ['class' => 'btn btn-outline-primary btn-sm']) ?>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>
