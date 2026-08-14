<?php

use yii\helpers\Html;

/* @var $this yii\web\View */
/* @var $manifest array<string, mixed> */
/* @var $roles list<string> */
/* @var $inAuthItem bool */

$capabilityId = (string) ($manifest['capability_id'] ?? $manifest['key'] ?? '');
$key = (string) ($manifest['key'] ?? $capabilityId);

$this->title = 'Capability: ' . $capabilityId;
$this->params['breadcrumbs'][] = ['label' => 'Catálogo de permisos', 'url' => ['index', 'tab' => 'capabilities']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="permission-catalog-view-capability">

    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <h1 class="h2 mb-0"><?= Html::encode($this->title) ?></h1>
        <div class="d-flex gap-2">
            <?= Html::a('Volver al catálogo', ['index', 'tab' => 'capabilities'], ['class' => 'btn btn-outline-secondary btn-sm']) ?>
            <?= Html::a('Editar roles', ['edit-capability-roles', 'key' => $key], ['class' => 'btn btn-primary btn-sm']) ?>
        </div>
    </div>

    <?php if (!$inAuthItem): ?>
        <div class="alert alert-warning py-2 small">
            Esta capability no está en <code>auth_item</code>.
            <?= Html::a('Sincronizar capabilities', ['sync-capabilities'], [
                'class' => 'btn btn-sm btn-warning ms-2',
                'data' => ['method' => 'post'],
            ]) ?>
        </div>
    <?php endif; ?>

    <div class="card mb-3">
        <div class="card-body">
            <h2 class="h5"><?= Html::encode((string) ($manifest['description'] ?? $capabilityId)) ?></h2>
            <p class="small mb-2">
                <strong>ID:</strong> <code><?= Html::encode($capabilityId) ?></code>
            </p>
            <p class="small mb-0">
                <strong>Roles con acceso:</strong>
                <?= $roles === [] ? '—' : Html::encode(implode(', ', $roles)) ?>
            </p>
        </div>
    </div>

    <?php
    $routes = is_array($manifest['routes'] ?? null) ? $manifest['routes'] : [];
    if ($routes !== []):
        ?>
        <div class="card mb-3">
            <div class="card-header"><strong>Rutas API</strong></div>
            <div class="card-body p-0">
                <ul class="list-group list-group-flush small">
                    <?php foreach ($routes as $route): ?>
                        <li class="list-group-item py-1"><code><?= Html::encode((string) $route) ?></code></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    <?php endif; ?>

    <?php
    $defaultRoles = is_array($manifest['default_roles'] ?? null) ? $manifest['default_roles'] : [];
    if ($defaultRoles !== []):
        ?>
        <div class="card mb-3">
            <div class="card-header"><strong>Roles por defecto (YAML)</strong></div>
            <div class="card-body small">
                <?= Html::encode(implode(', ', array_map('strval', $defaultRoles))) ?>
            </div>
        </div>
    <?php endif; ?>

    <?php
    $relatedIntents = is_array($manifest['related_intents'] ?? null) ? $manifest['related_intents'] : [];
    if ($relatedIntents !== []):
        ?>
        <div class="card mb-3">
            <div class="card-header"><strong>Intents relacionados</strong></div>
            <div class="card-body p-0">
                <ul class="list-group list-group-flush small">
                    <?php foreach ($relatedIntents as $intentId): ?>
                        <li class="list-group-item py-1 d-flex justify-content-between align-items-center">
                            <code><?= Html::encode((string) $intentId) ?></code>
                            <?= Html::a('Ver', ['view-intent', 'intent_id' => (string) $intentId], [
                                'class' => 'btn btn-outline-secondary btn-sm py-0',
                            ]) ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    <?php endif; ?>

</div>
