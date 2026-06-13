<?php

use yii\helpers\Html;

/* @var $this yii\web\View */
/* @var $matrix list<array{key: string, kind: string, source: string, in_auth_item: bool, roles: list<string>}> */
/* @var $roleNames list<string> */

$this->title = 'Roles ↔ permisos del catálogo';
$this->params['breadcrumbs'][] = ['label' => 'Catálogo de permisos', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;

$unregistered = array_filter($matrix, static fn (array $r): bool => !$r['in_auth_item'] && strncmp($r['key'], '/api/', 5) !== 0);
$unassigned = array_filter($matrix, static fn (array $r): bool => $r['roles'] === []);
?>
<div class="permission-catalog-roles">

    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <h1 class="h2 mb-0"><?= Html::encode($this->title) ?></h1>
        <div class="d-flex flex-wrap gap-2 align-items-center">
            <?= Html::beginForm(['sync'], 'post', ['class' => 'd-inline-flex align-items-center gap-2']) ?>
                <label class="form-check-label small mb-0">
                    <input type="checkbox" name="deactivate_legacy_grants" value="1" class="form-check-input">
                    Desactivar grants legacy
                </label>
                <?= Html::submitButton('Sincronizar catálogo + grants', [
                    'class' => 'btn btn-warning btn-sm',
                    'data' => ['confirm' => '¿Registrar permisos del catálogo en auth_item y migrar data_access_role_grant?'],
                ]) ?>
            <?= Html::endForm() ?>
            <?= Html::beginForm(['migrate-grants'], 'post', ['class' => 'd-inline-flex align-items-center gap-2']) ?>
                <label class="form-check-label small mb-0">
                    <input type="checkbox" name="deactivate_legacy_grants" value="1" class="form-check-input">
                    Desactivar legacy
                </label>
                <?= Html::submitButton('Solo migrar grants', [
                    'class' => 'btn btn-outline-warning btn-sm',
                    'data' => ['confirm' => '¿Migrar data_access_role_grant → auth_item (sin sync de intents)?'],
                ]) ?>
            <?= Html::endForm() ?>
            <?= Html::a('Catálogo', ['index'], ['class' => 'btn btn-outline-secondary btn-sm']) ?>
            <?= Html::a('Integridad', ['integrity'], ['class' => 'btn btn-outline-primary btn-sm']) ?>
        </div>
    </div>

    <p class="text-muted">
        Cruce declarativo (intents + atributos) con <code>auth_item</code> y grants legacy
        <code>data_access_role_grant</code>. La migración crea permisos atómicos
        <code>Entidad.atributo.read|info|edit</code> y enlaces rol → permiso.
        <?= Html::a('Panel legacy de grants', ['/data-access-grant/index'], ['class' => 'ms-1']) ?>
        queda en desuso tras migrar.
    </p>

    <?php if ($unregistered !== []): ?>
        <div class="alert alert-warning">
            <strong><?= count($unregistered) ?></strong> clave(s) de permiso lógico sin fila en <code>auth_item</code>.
        </div>
    <?php endif; ?>

    <?php if ($unassigned !== []): ?>
        <div class="alert alert-info">
            <strong><?= count($unassigned) ?></strong> clave(s) sin rol asignado (vía auth_item_child o data_access_role_grant).
        </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-body p-0">
            <table class="table table-sm table-striped mb-0">
                <thead>
                <tr>
                    <th>Permiso</th>
                    <th>Tipo</th>
                    <th>Origen</th>
                    <th>auth_item</th>
                    <th>Roles</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($matrix as $row): ?>
                    <?php
                    $missingItem = !$row['in_auth_item'] && strncmp($row['key'], '/api/', 5) !== 0;
                    $missingRoles = $row['roles'] === [];
                    ?>
                    <tr class="<?= $missingItem ? 'table-warning' : ($missingRoles ? 'table-light' : '') ?>">
                        <td><code><?= Html::encode($row['key']) ?></code></td>
                        <td><?= Html::encode($row['kind']) ?></td>
                        <td class="small text-muted"><?= Html::encode($row['source']) ?></td>
                        <td><?= $row['in_auth_item'] || strncmp($row['key'], '/api/', 5) === 0 ? '✓' : '—' ?></td>
                        <td class="small">
                            <?= $row['roles'] === [] ? '—' : Html::encode(implode(', ', $row['roles'])) ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <p class="text-muted small mt-3">
        Roles en sistema:
        <?php foreach ($roleNames as $rn): ?>
            <?= Html::a(Html::encode($rn), ['edit-role', 'role' => $rn], ['class' => 'badge bg-secondary text-decoration-none me-1']) ?>
        <?php endforeach; ?>
        <?= $roleNames === [] ? '—' : '' ?>
    </p>
</div>
