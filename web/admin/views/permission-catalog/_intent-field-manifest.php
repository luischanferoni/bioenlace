<?php

use yii\helpers\Html;

/* @var $this yii\web\View */
/* @var $manifest array<string, mixed> */
?>
<?php
$fields = is_array($manifest['fields'] ?? null) ? $manifest['fields'] : [];
$flowFields = is_array($manifest['flow_fields'] ?? null) ? $manifest['flow_fields'] : [];
$fieldGroups = is_array($manifest['field_groups'] ?? null) ? $manifest['field_groups'] : null;
$openUiSteps = is_array($manifest['open_ui_steps'] ?? null) ? $manifest['open_ui_steps'] : [];
?>
<div class="card mb-3">
    <div class="card-header"><strong>Manifiesto YAML</strong> <span class="text-muted small">(solo lectura)</span></div>
    <div class="card-body">
        <dl class="row small mb-0">
            <?php if (!empty($manifest['operation'])): ?>
                <dt class="col-sm-3">operation</dt>
                <dd class="col-sm-9"><code><?= Html::encode((string) $manifest['operation']) ?></code></dd>
            <?php endif; ?>
            <?php if (!empty($manifest['intent_family'])): ?>
                <dt class="col-sm-3">intent_family</dt>
                <dd class="col-sm-9"><code><?= Html::encode((string) $manifest['intent_family']) ?></code></dd>
            <?php endif; ?>
            <?php if (!empty($manifest['domain_operation'])): ?>
                <dt class="col-sm-3">domain_operation</dt>
                <dd class="col-sm-9"><code><?= Html::encode((string) $manifest['domain_operation']) ?></code></dd>
            <?php endif; ?>
            <?php if (!empty($manifest['rbac_route'])): ?>
                <dt class="col-sm-3">rbac_route</dt>
                <dd class="col-sm-9"><code><?= Html::encode((string) $manifest['rbac_route']) ?></code></dd>
            <?php endif; ?>
            <?php if (is_array($manifest['subject_resolution'] ?? null) && $manifest['subject_resolution'] !== []): ?>
                <dt class="col-sm-3">subject_resolution</dt>
                <dd class="col-sm-9"><code><?= Html::encode(json_encode($manifest['subject_resolution'], JSON_UNESCAPED_UNICODE)) ?></code></dd>
            <?php endif; ?>
            <?php if (is_array($manifest['open_ui'] ?? null) && $manifest['open_ui'] !== []): ?>
                <dt class="col-sm-3">open_ui</dt>
                <dd class="col-sm-9"><code><?= Html::encode(json_encode($manifest['open_ui'], JSON_UNESCAPED_UNICODE)) ?></code></dd>
            <?php endif; ?>
        </dl>

        <?php if ($fields !== []): ?>
            <h6 class="mt-3">fields</h6>
            <table class="table table-sm table-bordered mb-0">
                <thead>
                <tr>
                    <th>Campo</th>
                    <th>Keywords NL</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($fields as $field): ?>
                    <?php if (!is_array($field)) {
                        continue;
                    } ?>
                    <tr>
                        <td><code><?= Html::encode((string) ($field['name'] ?? '')) ?></code></td>
                        <td class="small text-muted">
                            <?php
                            $kws = is_array($field['keywords'] ?? null) ? $field['keywords'] : [];
                            echo $kws === [] ? '—' : Html::encode(implode(', ', $kws));
                            ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <?php if ($fieldGroups !== null && $fieldGroups !== []): ?>
            <h6 class="mt-3">field_groups</h6>
            <table class="table table-sm table-bordered mb-0">
                <thead>
                <tr>
                    <th>Grupo</th>
                    <th>Label</th>
                    <th>Campos</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($fieldGroups as $groupKey => $def): ?>
                    <?php if (!is_array($def)) {
                        continue;
                    } ?>
                    <tr>
                        <td><code><?= Html::encode((string) $groupKey) ?></code></td>
                        <td><?= Html::encode((string) ($def['label'] ?? '')) ?></td>
                        <td class="small">
                            <?php
                            $gf = is_array($def['fields'] ?? null) ? $def['fields'] : [];
                            echo Html::encode(implode(', ', array_map('strval', $gf)));
                            ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <?php if ($flowFields !== []): ?>
            <h6 class="mt-3">flow_submit (campos)</h6>
            <p class="small mb-0"><code><?= Html::encode(implode(', ', $flowFields)) ?></code></p>
        <?php endif; ?>

        <?php if ($openUiSteps !== []): ?>
            <h6 class="mt-3">Pasos open_ui</h6>
            <ul class="small mb-0">
                <?php foreach ($openUiSteps as $step): ?>
                    <?php if (!is_array($step)) {
                        continue;
                    } ?>
                    <li>
                        <code><?= Html::encode((string) ($step['step_id'] ?? '')) ?></code>
                        → <code><?= Html::encode((string) ($step['action_id'] ?? '')) ?></code>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <?php if ($fields === [] && $flowFields === [] && ($fieldGroups === null || $fieldGroups === [])): ?>
            <p class="text-muted small mb-0">Este intent no declara <code>fields</code> ni <code>flow_submit</code> en su YAML.</p>
        <?php endif; ?>
    </div>
</div>
