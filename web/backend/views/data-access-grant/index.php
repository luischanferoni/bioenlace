<?php

use yii\helpers\Html;
use yii\grid\GridView;

/* @var $this yii\web\View */
/* @var $dataProvider yii\data\ActiveDataProvider */
/* @var $orphanRoleGrants array<string, int> */

$this->title = 'Permisos por atributo (grants BD)';
$this->params['breadcrumbs'][] = ['label' => 'Consultas staff', 'url' => ['#']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="data-access-grant-index">

    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h2 class="card-title mt-1 mb-0"><?= Html::encode($this->title) ?></h2>
                <div>
                    <?= Html::a('Campos BD', ['data-access-attribute-field/index'], ['class' => 'btn btn-outline-secondary btn-sm']) ?>
                    <?= Html::a('Catálogo', ['data-access-catalog/index'], ['class' => 'btn btn-outline-secondary btn-sm']) ?>
                    <?= Html::a('Nuevo grant', ['create'], ['class' => 'btn btn-success btn-sm']) ?>
                </div>
            </div>
            <div class="card-body">
                <div class="alert alert-info" role="alert">
                    <strong>Nuevo panel RBAC:</strong>
                    los grants por grupo se migran a permisos atómicos en
                    <?= Html::a('Catálogo de permisos → Roles', ['/permission-catalog/roles'], ['class' => 'alert-link']) ?>.
                    Este CRUD legacy sigue activo como respaldo hasta completar la migración.
                </div>

                <p class="text-muted small">
                    Permisos por rol y grupo de atributos. Los grupos deben existir en
                    <code>data-access-config</code> (junto a los intents <code>data-access.*</code>).
                </p>

                <?php if ($orphanRoleGrants !== []): ?>
                    <div class="alert alert-warning" role="alert">
                        <strong>Roles huérfanos:</strong> hay grants asignados a roles que ya no existen en webvimark.
                        <ul class="mb-0 mt-2">
                            <?php foreach ($orphanRoleGrants as $roleName => $count): ?>
                                <li>
                                    <code><?= Html::encode($roleName) ?></code>
                                    — <?= (int) $count ?> grant<?= $count === 1 ? '' : 's' ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                        <p class="small mb-0 mt-2">
                            Reasigná o desactivá esos grants; no aplican a ningún usuario actual.
                        </p>
                    </div>
                <?php endif; ?>

                <?= GridView::widget([
                    'dataProvider' => $dataProvider,
                    'columns' => [
                        'id',
                        'role_name',
                        'entity_group_key',
                        'operations_csv',
                        'scope_checker',
                        [
                            'attribute' => 'active',
                            'format' => 'raw',
                            'value' => static function ($model) {
                                return (int) $model->active === 1
                                    ? '<span class="badge bg-success">Sí</span>'
                                    : '<span class="badge bg-secondary">No</span>';
                            },
                        ],
                        [
                            'attribute' => 'notas',
                            'contentOptions' => ['style' => 'max-width: 200px; white-space: normal;'],
                        ],
                        ['class' => 'yii\grid\ActionColumn'],
                    ],
                ]); ?>
            </div>
        </div>
    </div>
</div>
