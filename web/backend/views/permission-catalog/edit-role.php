<?php

use yii\helpers\Html;
use yii\widgets\ActiveForm;

/* @var $this yii\web\View */
/* @var $roleName string */
/* @var $permissions list<array{key: string, kind: string, description: string, assigned: bool, in_auth_item: bool}> */

$this->title = 'Permisos del rol: ' . $roleName;
$this->params['breadcrumbs'][] = ['label' => 'Catálogo de permisos', 'url' => ['index']];
$this->params['breadcrumbs'][] = ['label' => 'Roles ↔ permisos', 'url' => ['roles']];
$this->params['breadcrumbs'][] = $this->title;

$intents = array_values(array_filter($permissions, static fn (array $p): bool => ($p['kind'] ?? '') === 'intent'));
$attributes = array_values(array_filter($permissions, static fn (array $p): bool => ($p['kind'] ?? '') !== 'intent'));
$missingAuth = array_filter($permissions, static fn (array $p): bool => empty($p['in_auth_item']));
?>
<div class="permission-catalog-edit-role">

    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <h1 class="h2 mb-0"><?= Html::encode($this->title) ?></h1>
        <?= Html::a('Volver a matriz', ['roles'], ['class' => 'btn btn-outline-secondary btn-sm']) ?>
    </div>

    <?php if ($missingAuth !== []): ?>
        <div class="alert alert-warning">
            Hay permisos del catálogo sin fila en <code>auth_item</code>.
            <?= Html::a('Sincronizar catálogo → auth_item', ['sync'], [
                'class' => 'btn btn-sm btn-warning ms-2',
                'data' => ['method' => 'post'],
            ]) ?>
        </div>
    <?php endif; ?>

    <?php $form = ActiveForm::begin(['method' => 'post']); ?>

    <div class="row">
        <div class="col-lg-6 mb-4">
            <div class="card">
                <div class="card-header"><strong>Intents / operaciones</strong> (<?= count($intents) ?>)</div>
                <div class="card-body" style="max-height: 420px; overflow-y: auto;">
                    <?php foreach ($intents as $perm): ?>
                        <?php $id = 'perm_' . md5($perm['key']); ?>
                        <div class="form-check mb-1">
                            <input class="form-check-input" type="checkbox" name="permissions[]"
                                   value="<?= Html::encode($perm['key']) ?>" id="<?= $id ?>"
                                   <?= $perm['assigned'] ? 'checked' : '' ?>
                                   <?= !$perm['in_auth_item'] ? 'disabled' : '' ?>>
                            <label class="form-check-label small" for="<?= $id ?>">
                                <code><?= Html::encode($perm['key']) ?></code>
                                <?php if ($perm['description'] !== '' && $perm['description'] !== $perm['key']): ?>
                                    <span class="text-muted">— <?= Html::encode($perm['description']) ?></span>
                                <?php endif; ?>
                            </label>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div class="col-lg-6 mb-4">
            <div class="card">
                <div class="card-header"><strong>Atributos</strong> (<?= count($attributes) ?>)</div>
                <div class="card-body" style="max-height: 420px; overflow-y: auto;">
                    <?php foreach ($attributes as $perm): ?>
                        <?php $id = 'perm_' . md5($perm['key']); ?>
                        <div class="form-check mb-1">
                            <input class="form-check-input" type="checkbox" name="permissions[]"
                                   value="<?= Html::encode($perm['key']) ?>" id="<?= $id ?>"
                                   <?= $perm['assigned'] ? 'checked' : '' ?>
                                   <?= !$perm['in_auth_item'] ? 'disabled' : '' ?>>
                            <label class="form-check-label small" for="<?= $id ?>">
                                <code><?= Html::encode($perm['key']) ?></code>
                            </label>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="mb-3">
        <?= Html::submitButton('Guardar permisos', ['class' => 'btn btn-primary']) ?>
    </div>

    <?php ActiveForm::end(); ?>
</div>
