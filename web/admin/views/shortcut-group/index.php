<?php

use yii\helpers\Html;

/* @var $this yii\web\View */
/* @var $rows common\models\Platform\AssistantShortcutGroup[] */

$this->title = 'Grupos de atajos del asistente';
$this->params['breadcrumbs'][] = ['label' => 'Catálogo de permisos', 'url' => ['/permission-catalog/index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="shortcut-group-index">

    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
            <h1 class="h2 mb-0"><?= Html::encode($this->title) ?></h1>
            <p class="text-muted small mb-0 mt-1">
                Los intents visibles los define <strong>rol ↔ intent</strong> en el catálogo de permisos.
                Acá solo se editan <strong>título y orden</strong> de cada familia (<code>urgencias.*</code>, etc.).
            </p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <?= Html::a('Catálogo de permisos', ['/permission-catalog/index'], ['class' => 'btn btn-outline-secondary btn-sm']) ?>
            <?= Html::a('Nuevo grupo', ['create'], ['class' => 'btn btn-primary btn-sm']) ?>
            <?= Html::beginForm(['reseed-yaml'], 'post', ['class' => 'd-inline']) ?>
                <?= Html::submitButton('Importar desde YAML', [
                    'class' => 'btn btn-outline-warning btn-sm',
                    'data' => ['confirm' => '¿Sobrescribir etiquetas/orden desde assistant-shortcut-group-labels.yaml?'],
                ]) ?>
            <?= Html::endForm() ?>
        </div>
    </div>

    <div class="card">
        <div class="card-body p-0">
            <table class="table table-sm table-striped mb-0">
                <thead>
                <tr>
                    <th>Prefijo (group_id)</th>
                    <th>Título</th>
                    <th class="text-end">Orden</th>
                    <th>Actualizado</th>
                    <th class="text-end">Acciones</th>
                </tr>
                </thead>
                <tbody>
                <?php if ($rows === []): ?>
                    <tr>
                        <td colspan="5" class="text-muted p-3">
                            Sin filas en BD. Ejecutá la migración o «Importar desde YAML».
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <td><code><?= Html::encode($row->group_id) ?></code></td>
                            <td><?= Html::encode($row->label) ?></td>
                            <td class="text-end"><?= (int) $row->sort_order ?></td>
                            <td class="small text-muted"><?= Html::encode((string) $row->updated_at) ?></td>
                            <td class="text-end text-nowrap">
                                <?= Html::a('Editar', ['update', 'group_id' => $row->group_id], ['class' => 'btn btn-link btn-sm p-0 me-2']) ?>
                                <?= Html::beginForm(['delete', 'group_id' => $row->group_id], 'post', ['class' => 'd-inline']) ?>
                                    <?= Html::submitButton('Eliminar', [
                                        'class' => 'btn btn-link btn-sm text-danger p-0',
                                        'data' => ['confirm' => '¿Eliminar este grupo?'],
                                    ]) ?>
                                <?= Html::endForm() ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
