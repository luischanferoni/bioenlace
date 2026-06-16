<?php

use yii\helpers\Html;

/* @var $this yii\web\View */
/* @var $manifest array<string, mixed> */
/* @var $roles list<string> */
/* @var $inAuthItem bool */

$intentId = (string) ($manifest['intent_id'] ?? '');
$key = (string) ($manifest['key'] ?? $intentId);

$this->title = 'Intent: ' . $intentId;
$this->params['breadcrumbs'][] = ['label' => 'Catálogo de permisos', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="permission-catalog-view-intent">

    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <h1 class="h2 mb-0"><?= Html::encode($this->title) ?></h1>
        <div class="d-flex gap-2">
            <?= Html::a('Volver al catálogo', ['index'], ['class' => 'btn btn-outline-secondary btn-sm']) ?>
            <?= Html::a('Editar roles', ['edit-intent-roles', 'key' => $key], ['class' => 'btn btn-primary btn-sm']) ?>
        </div>
    </div>

    <?php if (!$inAuthItem): ?>
        <div class="alert alert-warning py-2 small">
            Este intent no está en <code>auth_item</code>.
            <?= Html::a('Sincronizar', ['sync'], ['class' => 'btn btn-sm btn-warning ms-2', 'data' => ['method' => 'post']]) ?>
        </div>
    <?php endif; ?>

    <div class="card mb-3">
        <div class="card-body">
            <h2 class="h5"><?= Html::encode((string) ($manifest['action_name'] ?? $intentId)) ?></h2>
            <?php if (!empty($manifest['path'])): ?>
                <p class="small text-muted mb-2">
                    Archivo: <code><?= Html::encode(basename((string) $manifest['path'])) ?></code>
                </p>
            <?php endif; ?>
            <p class="small mb-0">
                <strong>Roles con acceso:</strong>
                <?= $roles === [] ? '—' : Html::encode(implode(', ', $roles)) ?>
            </p>
        </div>
    </div>

    <?= $this->render('_intent-field-manifest', ['manifest' => $manifest]) ?>

    <?php
    $semantics = is_array($manifest['intent_semantics'] ?? null) ? $manifest['intent_semantics'] : null;
    if ($semantics !== null && $semantics !== []):
        ?>
        <div class="card">
            <div class="card-header"><strong>intent_semantics</strong></div>
            <div class="card-body small">
                <?php foreach ($semantics as $semKey => $semVal): ?>
                    <?php if (!is_string($semVal) && !is_array($semVal)) {
                        continue;
                    } ?>
                    <p class="mb-2">
                        <strong><?= Html::encode((string) $semKey) ?>:</strong><br>
                        <?php if (is_array($semVal)): ?>
                            <?= Html::encode(implode('; ', array_map('strval', $semVal))) ?>
                        <?php else: ?>
                            <?= nl2br(Html::encode($semVal)) ?>
                        <?php endif; ?>
                    </p>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
</div>
