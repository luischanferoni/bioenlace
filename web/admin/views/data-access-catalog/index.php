<?php

use yii\helpers\Html;

/* @var $this yii\web\View */
/* @var $configDirectory string */
/* @var $entityGroups array<string, string> */
/* @var $entities array<string, array<string, mixed>> */
/* @var $metrics array<string, array<string, mixed>> */
/* @var $editSurfaces array<string, array<string, mixed>> */

$this->title = 'Catálogo DataAccess';
$this->params['breadcrumbs'][] = ['label' => 'Consultas staff', 'url' => ['#']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="data-access-catalog-index">

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h2 mb-0"><?= Html::encode($this->title) ?></h1>
        <?= Html::a('Catálogo de permisos', ['permission-catalog/index'], ['class' => 'btn btn-outline-primary btn-sm']) ?>
        <?= Html::a('Roles RBAC', ['permission-catalog/roles'], ['class' => 'btn btn-outline-secondary btn-sm']) ?>
    </div>

    <p class="text-muted">
        Métricas y flujos en <code><?= Html::encode($configDirectory) ?></code> (referencia técnica).
        La asignación de permisos es por <strong>intent</strong> en <?= Html::a('Catálogo de permisos', ['permission-catalog/index']) ?>.
    </p>

    <div class="card mb-4">
        <div class="card-header"><strong>Grupos de atributos</strong> <span class="text-muted small">(unión YAML + BD)</span></div>
        <div class="card-body p-0">
            <table class="table table-sm table-striped mb-0">
                <thead>
                    <tr>
                        <th>Grupo</th>
                        <th>Campos</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($entityGroups as $fullKey => $label): ?>
                    <tr>
                        <td><code><?= Html::encode((string) $fullKey) ?></code></td>
                        <td><?= Html::encode((string) $label) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header"><strong>Métricas registradas</strong></div>
        <div class="card-body p-0">
            <table class="table table-sm table-striped mb-0">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Etiqueta</th>
                        <th>Scope</th>
                        <th>Modos salida</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($metrics as $metricId => $def): ?>
                    <?php if (!is_array($def)) {
                        continue;
                    } ?>
                    <?php
                    $output = isset($def['query']['output']) && is_array($def['query']['output']) ? $def['query']['output'] : [];
                    $modes = isset($output['modes']) && is_array($output['modes']) ? implode(', ', $output['modes']) : '';
                    ?>
                    <tr>
                        <td><code><?= Html::encode((string) $metricId) ?></code></td>
                        <td><?= Html::encode((string) ($def['label'] ?? '')) ?></td>
                        <td><code><?= Html::encode((string) ($def['scope_checker'] ?? '')) ?></code></td>
                        <td><?= Html::encode($modes) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header"><strong>Superficies de edición</strong></div>
        <div class="card-body p-0">
            <table class="table table-sm table-striped mb-0">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Etiqueta</th>
                        <th>Datos (aspectos)</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($editSurfaces as $surfaceId => $def): ?>
                    <?php if (!is_array($def)) {
                        continue;
                    } ?>
                    <?php
                    $aspects = isset($def['aspects']) && is_array($def['aspects']) ? array_keys($def['aspects']) : [];
                    ?>
                    <tr>
                        <td><code><?= Html::encode((string) $surfaceId) ?></code></td>
                        <td><?= Html::encode((string) ($def['label'] ?? '')) ?></td>
                        <td><?= Html::encode(implode(', ', $aspects)) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>
