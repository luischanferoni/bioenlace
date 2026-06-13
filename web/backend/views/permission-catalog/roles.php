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
        <?= Html::a('Catálogo', ['index'], ['class' => 'btn btn-outline-secondary btn-sm']) ?>
        <?= Html::a('Integridad', ['integrity'], ['class' => 'btn btn-outline-primary btn-sm']) ?>
    </div>

    <p class="text-muted">
        Cruce declarativo (intents + atributos) con <code>auth_item</code> y grants legacy <code>data_access_role_grant</code>.
        Permisos lógicos greenfield (p. ej. <code>Turno.create</code>) aparecen como «sin auth_item» hasta migración RBAC.
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
        Roles en sistema: <?= Html::encode(implode(', ', $roleNames)) ?: '—' ?>
    </p>
</div>
