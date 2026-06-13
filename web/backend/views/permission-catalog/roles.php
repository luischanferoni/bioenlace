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
            <?= Html::beginForm(['sync'], 'post', ['class' => 'd-inline']) ?>
                <?= Html::submitButton('Sincronizar catálogo → auth_item', [
                    'class' => 'btn btn-warning btn-sm',
                    'data' => ['confirm' => '¿Registrar permisos del catálogo en auth_item y heredar asignaciones por ruta?'],
                ]) ?>
            <?= Html::endForm() ?>
            <?= Html::a('Catálogo', ['index'], ['class' => 'btn btn-outline-secondary btn-sm']) ?>
            <?= Html::a('Integridad', ['integrity'], ['class' => 'btn btn-outline-primary btn-sm']) ?>
        </div>
    </div>

    <p class="text-muted">
        Cruce declarativo (intents + atributos) con <code>auth_item</code>.
        Los permisos atómicos son <code>Entidad.atributo.read|info|edit</code> y los intents lógicos
        (<code>Turno.create</code>, etc.) con enlaces rol → permiso.
    </p>
