<?php

use yii\helpers\Html;
use yii\grid\GridView;

/* @var $this yii\web\View */
/* @var $dataProvider yii\data\ActiveDataProvider */
/* @var $groupFilter string */
/* @var $entityGroups array<string, string> */

$this->title = 'Campos por atributo (BD)';
$this->params['breadcrumbs'][] = ['label' => 'Consultas staff', 'url' => ['#']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="data-access-attribute-field-index">

    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h2 class="card-title mt-1 mb-0"><?= Html::encode($this->title) ?></h2>
                <div>
                    <?= Html::a('Roles RBAC', ['/user-management/role/index'], ['class' => 'btn btn-outline-secondary btn-sm']) ?>
                    <?= Html::a('Catálogo', ['data-access-catalog/index'], ['class' => 'btn btn-outline-secondary btn-sm']) ?>
                    <?= Html::a('Nuevo campo', ['create', 'group' => $groupFilter], ['class' => 'btn btn-success btn-sm']) ?>
                </div>
            </div>
            <div class="card-body">
                <p class="text-muted small">
                    Esquema de formularios de edición dispersa. El YAML solo registra el grupo
                    (p. ej. <code>agenda_horarios: {}</code> en YAML); los campos viven aquí.
                </p>

                <?= Html::beginForm(['index'], 'get', ['class' => 'row g-2 align-items-end mb-3']) ?>
                <div class="col-md-8">
                    <label class="form-label small mb-1" for="group-filter">Filtrar por clave YAML</label>
                    <?= Html::dropDownList('group', $groupFilter, $entityGroups, [
                        'id' => 'group-filter',
                        'class' => 'form-select form-select-sm',
                        'prompt' => 'Todas las claves',
                    ]) ?>
                </div>
                <div class="col-md-4">
                    <?= Html::submitButton('Filtrar', ['class' => 'btn btn-primary btn-sm']) ?>
                    <?php if ($groupFilter !== ''): ?>
                        <?= Html::a('Quitar filtro', ['index'], ['class' => 'btn btn-outline-secondary btn-sm']) ?>
                    <?php endif; ?>
                </div>
                <?= Html::endForm() ?>

                <?= GridView::widget([
                    'dataProvider' => $dataProvider,
                    'columns' => [
                        'entity_group_key',
                        'field_name',
                        'field_type',
                        'label',
                        'sort_order',
                        [
                            'attribute' => 'active',
                            'value' => static fn ($model) => (int) $model->active === 1 ? 'Sí' : 'No',
                        ],
                        [
                            'class' => 'yii\grid\ActionColumn',
                            'template' => '{view} {update}',
                        ],
                    ],
                ]) ?>
            </div>
        </div>
    </div>
</div>
