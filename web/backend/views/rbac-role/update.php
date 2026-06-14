<?php

use yii\helpers\Html;
use yii\widgets\ActiveForm;

/* @var $this yii\web\View */
/* @var $role common\models\rbac\AuthRole */
/* @var $intents list<array{key: string, description: string, assigned: bool, in_auth_item: bool}> */

$this->title = 'Rol: ' . $role->name;
$this->params['breadcrumbs'][] = ['label' => 'Roles RBAC', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;

$missingAuth = array_filter($intents, static fn (array $p): bool => empty($p['in_auth_item']));
?>
<div class="rbac-role-update">

    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <h1 class="h2 mb-0"><?= Html::encode($this->title) ?></h1>
        <?= Html::a('Volver al listado', ['index'], ['class' => 'btn btn-outline-secondary btn-sm']) ?>
    </div>

    <?php if ($missingAuth !== []): ?>
        <div class="alert alert-warning py-2 small">
            Hay intents sin fila en <code>auth_item</code>.
            <?= Html::a('Sincronizar catálogo', ['/permission-catalog/sync'], [
                'class' => 'btn btn-sm btn-warning ms-2',
                'data' => ['method' => 'post'],
            ]) ?>
        </div>
    <?php endif; ?>

    <?php $form = ActiveForm::begin(['method' => 'post']); ?>

    <div class="mb-3">
        <label class="form-label">Nombre</label>
        <input type="text" class="form-control" readonly value="<?= Html::encode($role->name) ?>">
    </div>

    <div class="mb-3">
        <label class="form-label" for="role-description">Descripción</label>
        <input type="text" class="form-control" id="role-description" name="description"
               value="<?= Html::encode((string) $role->description) ?>">
    </div>

    <div class="card mb-3">
        <div class="card-header"><strong>Intents asignados</strong> (<?= count($intents) ?>)</div>
        <div class="card-body" style="max-height: 480px; overflow-y: auto;">
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

    <?= Html::submitButton('Guardar', ['class' => 'btn btn-primary']) ?>

    <?php ActiveForm::end(); ?>
</div>
