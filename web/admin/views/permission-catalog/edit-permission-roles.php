<?php

use yii\helpers\Html;
use yii\widgets\ActiveForm;

/* @var $this yii\web\View */
/* @var $permissionKey string */
/* @var $catalogRow array<string, mixed> */
/* @var $fieldManifest array<string, mixed>|null */
/* @var $roleNames list<string> */
/* @var $assignedRoles array<string, int> */
/* @var $inAuthItem bool */

$this->title = 'Roles: ' . $permissionKey;
$this->params['breadcrumbs'][] = ['label' => 'Catálogo de permisos', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="permission-catalog-edit-permission-roles">

    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <h1 class="h2 mb-0"><?= Html::encode($this->title) ?></h1>
        <?= Html::a('Volver al catálogo', ['index'], ['class' => 'btn btn-outline-secondary btn-sm']) ?>
    </div>

    <?php if (!$inAuthItem): ?>
        <div class="alert alert-warning">
            Este intent no está en <code>auth_item</code>.
            <?= Html::a('Sincronizar catálogo', ['sync'], [
                'class' => 'btn btn-sm btn-warning ms-2',
                'data' => ['method' => 'post'],
            ]) ?>
        </div>
    <?php endif; ?>

    <p class="text-muted small">
        Intent <code><?= Html::encode((string) ($catalogRow['intent_id'] ?? '')) ?></code>
        <?php if (!empty($catalogRow['rbac_route'])): ?>
            · API <code><?= Html::encode((string) $catalogRow['rbac_route']) ?></code>
        <?php endif; ?>
        · <?= Html::a('Ver manifiesto completo', ['view-intent', 'intent_id' => (string) ($catalogRow['intent_id'] ?? '')]) ?>
    </p>

    <?php if (is_array($fieldManifest)): ?>
        <?= $this->render('_intent-field-manifest', ['manifest' => $fieldManifest]) ?>
    <?php endif; ?>

    <?php $form = ActiveForm::begin(['method' => 'post']); ?>

    <div class="card">
        <div class="card-header"><strong>Roles con este intent</strong></div>
        <div class="card-body">
            <?php foreach ($roleNames as $roleName): ?>
                <?php $id = 'role_' . md5($roleName); ?>
                <div class="form-check mb-2">
                    <input class="form-check-input" type="checkbox" name="roles[]"
                           value="<?= Html::encode($roleName) ?>" id="<?= $id ?>"
                           <?= isset($assignedRoles[$roleName]) ? 'checked' : '' ?>
                           <?= !$inAuthItem ? 'disabled' : '' ?>>
                    <label class="form-check-label" for="<?= $id ?>">
                        <code><?= Html::encode($roleName) ?></code>
                    </label>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="mt-3">
        <?= Html::submitButton('Guardar', ['class' => 'btn btn-primary', 'disabled' => !$inAuthItem]) ?>
    </div>

    <?php ActiveForm::end(); ?>
</div>
