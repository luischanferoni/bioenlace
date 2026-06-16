<?php

use yii\helpers\Html;

/* @var $this yii\web\View */
/* @var $roles list<common\models\rbac\AuthRole> */

$this->title = 'Roles RBAC';
$this->params['breadcrumbs'][] = ['label' => 'Catálogo de permisos', 'url' => ['/permission-catalog/index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="rbac-role-index">

    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <h1 class="h2 mb-0"><?= Html::encode($this->title) ?></h1>
        <?= Html::a('Nuevo rol', ['create'], ['class' => 'btn btn-primary btn-sm']) ?>
    </div>

    <p class="text-muted small">
        Los <strong>intents</strong> se asignan al editar cada rol.
        Los <strong>atributos</strong> se asignan desde el catálogo de permisos (pestaña Atributos).
    </p>

    <div class="card">
        <div class="card-body p-0">
            <table class="table table-sm table-striped mb-0">
                <thead>
                <tr>
                    <th>Nombre</th>
                    <th>Descripción</th>
                    <th class="text-end">Acciones</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($roles as $role): ?>
                    <tr>
                        <td><code><?= Html::encode($role->name) ?></code></td>
                        <td><?= Html::encode((string) $role->description) ?></td>
                        <td class="text-end text-nowrap">
                            <?= Html::a('Editar', ['update', 'name' => $role->name], ['class' => 'btn btn-outline-primary btn-sm']) ?>
                            <?php if (!in_array($role->name, \common\components\Platform\Core\Permission\RbacRoleAdminService::PROTECTED_ROLE_NAMES, true)): ?>
                                <?= Html::beginForm(['delete', 'name' => $role->name], 'post', ['class' => 'd-inline']) ?>
                                <?= Html::submitButton('Eliminar', [
                                    'class' => 'btn btn-outline-danger btn-sm',
                                    'data' => ['confirm' => '¿Eliminar el rol «' . $role->name . '»?'],
                                ]) ?>
                                <?= Html::endForm() ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
