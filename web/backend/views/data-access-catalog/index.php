<?php

use yii\helpers\Html;

/* @var $this yii\web\View */
/* @var $entityGroups array<string, string> */
/* @var $entities array<string, array<string, mixed>> */
/* @var $metrics array<string, array<string, mixed>> */
/* @var $yamlRoleGrants array<string, array<string, mixed>> */

$this->title = 'Catálogo DataAccess (YAML)';
$this->params['breadcrumbs'][] = ['label' => 'Consultas staff', 'url' => ['#']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="data-access-catalog-index">

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h2 mb-0"><?= Html::encode($this->title) ?></h1>
        <?= Html::a('Grants en BD', ['data-access-grant/index'], ['class' => 'btn btn-outline-primary btn-sm']) ?>
    </div>

    <p class="text-muted">
        Referencia de <code>common/components/Core/DataAccess/metadata/attribute_groups_v1.yaml</code>.
        Los grants en BD activos reemplazan estos valores por rol + grupo.
    </p>

    <div class="card mb-4">
        <div class="card-header"><strong>Grupos de atributos</strong></div>
        <div class="card-body p-0">
            <table class="table table-sm table-striped mb-0">
                <thead>
                    <tr>
                        <th>Grupo</th>
                        <th>Atributos</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($entities as $entityName => $groups): ?>
                    <?php if (!is_array($groups)) {
                        continue;
                    } ?>
                    <?php foreach ($groups as $groupKey => $def): ?>
                        <?php
                        $fullKey = $entityName . '.' . $groupKey;
                        $attrs = is_array($def) ? ($def['attributes'] ?? []) : [];
                        ?>
                        <tr>
                            <td><code><?= Html::encode($fullKey) ?></code></td>
                            <td><?= Html::encode(is_array($attrs) ? implode(', ', $attrs) : '') ?></td>
                        </tr>
                    <?php endforeach; ?>
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
        <div class="card-header"><strong>Grants YAML (referencia)</strong></div>
        <div class="card-body p-0">
            <table class="table table-sm table-striped mb-0">
                <thead>
                    <tr>
                        <th>Rol</th>
                        <th>Grupo</th>
                        <th>Operaciones</th>
                        <th>Scope checker</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($yamlRoleGrants as $roleName => $groups): ?>
                    <?php if (!is_array($groups)) {
                        continue;
                    } ?>
                    <?php foreach ($groups as $groupKey => $grant): ?>
                        <?php if (!is_array($grant)) {
                            continue;
                        } ?>
                        <?php
                        $ops = isset($grant['operations']) && is_array($grant['operations'])
                            ? implode(', ', $grant['operations'])
                            : '';
                        ?>
                        <tr>
                            <td><?= Html::encode((string) $roleName) ?></td>
                            <td><code><?= Html::encode((string) $groupKey) ?></code></td>
                            <td><?= Html::encode($ops) ?></td>
                            <td><code><?= Html::encode((string) ($grant['scope_checker'] ?? '')) ?></code></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>
