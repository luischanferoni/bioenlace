<?php

use yii\helpers\Html;

/* @var $this yii\web\View */
/* @var $intents list<array<string, mixed>> */
/* @var $capabilities list<array<string, mixed>> */
/* @var $deprecatedPermissions list<array<string, mixed>> */
/* @var $flowSteps list<array<string, mixed>> */
/* @var $rolesByKey array<string, list<string>> */
/* @var $intentInAuth array<string, bool> */
/* @var $capabilityInAuth array<string, bool> */
/* @var $roleNames list<string> */
/* @var $unregisteredIntentsCount int */
/* @var $unregisteredCapabilitiesCount int */

$tab = trim((string) Yii::$app->request->get('tab', 'intents'));
$activeIntents = $tab !== 'capabilities' && $tab !== 'flow-steps';
$activeCapabilities = $tab === 'capabilities';
$activeFlowSteps = $tab === 'flow-steps';

$this->title = 'Catálogo de permisos';
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="permission-catalog-index">

    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
            <h1 class="h2 mb-0"><?= Html::encode($this->title) ?></h1>
            <p class="text-muted small mb-0 mt-1">
                Asigná <strong>roles ↔ intents</strong> y <strong>capabilities UI nativa</strong>
                (guardia, encounter, panel). Los campos de intents se definen en su YAML.
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
            <?= Html::beginForm(['sync-capabilities'], 'post', ['class' => 'd-inline']) ?>
                <?= Html::submitButton('Sincronizar capabilities → auth_item', [
                    'class' => 'btn btn-warning btn-sm',
                    'data' => ['confirm' => '¿Registrar capabilities, rutas y grants por defecto?'],
                ]) ?>
            <?= Html::endForm() ?>
            <?= Html::a('Integridad', ['integrity'], ['class' => 'btn btn-primary btn-sm']) ?>
            <?= Html::a('Grupos de atajos', ['/shortcut-group/index'], ['class' => 'btn btn-outline-secondary btn-sm']) ?>
        </div>
    </div>

    <?php if ($unregisteredIntentsCount > 0): ?>
        <div class="alert alert-warning py-2 small">
            <?= (int) $unregisteredIntentsCount ?> intent(s) aún no están en <code>auth_item</code>.
            Ejecutá «Sincronizar intents → auth_item».
        </div>
    <?php endif; ?>

    <?php if ($unregisteredCapabilitiesCount > 0): ?>
        <div class="alert alert-warning py-2 small">
            <?= (int) $unregisteredCapabilitiesCount ?> capability(s) aún no están en <code>auth_item</code>.
            Ejecutá «Sincronizar capabilities → auth_item».
        </div>
    <?php endif; ?>

    <?php if (($deprecatedPermissions ?? []) !== []): ?>
        <div class="card mb-3 border-secondary">
            <div class="card-header py-2">
                <strong class="small">Permisos legacy (deprecados)</strong>
            </div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <thead>
                    <tr>
                        <th>Legacy</th>
                        <th>Reemplazo (capability)</th>
                        <th>Roles que aún lo tienen</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($deprecatedPermissions as $row): ?>
                        <?php
                        $legacyKey = (string) ($row['key'] ?? '');
                        $replacement = trim((string) ($row['replacement_capability'] ?? ''));
                        $legacyRoles = $rolesByKey[$legacyKey] ?? [];
                        ?>
                        <tr>
                            <td>
                                <code><?= Html::encode($legacyKey) ?></code>
                                <span class="badge bg-secondary ms-1">deprecated</span>
                            </td>
                            <td class="small">
                                <?php if ($replacement !== ''): ?>
                                    <?= Html::a(Html::encode($replacement), [
                                        'view-capability',
                                        'capability_id' => $replacement,
                                    ], ['class' => 'text-decoration-none']) ?>
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </td>
                            <td class="small">
                                <?= $legacyRoles === [] ? '—' : Html::encode(implode(', ', $legacyRoles)) ?>
                            </td>
                        </tr>
                        <?php if (!empty($row['note'])): ?>
                            <tr>
                                <td colspan="3" class="small text-muted py-1 border-0 pt-0">
                                    <?= Html::encode((string) $row['note']) ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="card-footer py-2 small text-muted">
                Migración idempotente:
                <code>php yii catalog-permission/migrate-grants</code>
            </div>
        </div>
    <?php endif; ?>

    <ul class="nav nav-tabs mb-3" role="tablist">
        <li class="nav-item">
            <a class="nav-link <?= $activeIntents ? 'active' : '' ?>" data-bs-toggle="tab" href="#tab-intents">
                Intents (<?= count($intents) ?>)
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $activeCapabilities ? 'active' : '' ?>" data-bs-toggle="tab" href="#tab-capabilities">
                Capabilities UI nativa (<?= count($capabilities) ?>)
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $activeFlowSteps ? 'active' : '' ?>" data-bs-toggle="tab" href="#tab-flow-steps">
                Pasos open_ui (<?= count($flowSteps) ?>)
            </a>
        </li>
    </ul>

    <div class="tab-content">
        <div class="tab-pane fade <?= $activeIntents ? 'show active' : '' ?>" id="tab-intents">
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

        <div class="tab-pane fade <?= $activeCapabilities ? 'show active' : '' ?>" id="tab-capabilities">
            <p class="text-muted small">
                Permisos para UIs nativas (web/móvil) no cubiertas por un solo intent: guardia, encounter y panel home.
            </p>
            <div class="card">
                <div class="card-body p-0">
                    <table class="table table-sm table-striped mb-0">
                        <thead>
                        <tr>
                            <th>Capability</th>
                            <th>Descripción</th>
                            <th>Rutas</th>
                            <th>Roles</th>
                            <th class="text-end">Acciones</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($capabilities as $row): ?>
                            <?php
                            $key = (string) ($row['key'] ?? $row['capability_id'] ?? '');
                            if ($key === '') {
                                continue;
                            }
                            $capabilityId = (string) ($row['capability_id'] ?? $key);
                            $routes = is_array($row['routes'] ?? null) ? $row['routes'] : [];
                            $roles = $rolesByKey[$key] ?? [];
                            $inAuth = $capabilityInAuth[$key] ?? false;
                            ?>
                            <tr class="<?= !$inAuth ? 'table-warning' : '' ?>">
                                <td><code><?= Html::encode($capabilityId) ?></code></td>
                                <td class="small"><?= Html::encode((string) ($row['description'] ?? '—')) ?></td>
                                <td class="small"><?= (int) count($routes) ?></td>
                                <td class="small">
                                    <?= $roles === [] ? '—' : Html::encode(implode(', ', $roles)) ?>
                                </td>
                                <td class="text-end text-nowrap">
                                    <?= Html::a('Detalle', ['view-capability', 'capability_id' => $capabilityId], [
                                        'class' => 'btn btn-outline-secondary btn-sm',
                                    ]) ?>
                                    <?= Html::a('Roles', ['edit-capability-roles', 'key' => $key], [
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

        <div class="tab-pane fade <?= $activeFlowSteps ? 'show active' : '' ?>" id="tab-flow-steps">
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
