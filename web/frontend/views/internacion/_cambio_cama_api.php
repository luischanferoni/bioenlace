<?php

use yii\helpers\Html;
use yii\helpers\Url;

/** @var common\models\SegNivelInternacion $model */
/** @var array<string, mixed> $ctx */

$camas = $ctx['camas_disponibles'] ?? [];
?>
<div id="internacion-cambio-cama-api"
     class="card border-info mb-3"
     data-internacion-id="<?= (int) $model->id ?>"
     data-redirect-url="<?= Html::encode(Url::to(['internacion/view', 'id' => $model->id])) ?>">
    <div class="card-header bg-soft-info">
        <strong>Cambio de cama</strong>
    </div>
    <div class="card-body">
        <?php if (!empty($ctx['cama_actual_label'])): ?>
            <p class="text-muted mb-2">Cama actual: <?= Html::encode((string) $ctx['cama_actual_label']) ?></p>
        <?php endif; ?>
        <div class="mb-2">
            <label class="form-label" for="cambio-cama-id-cama">Cama destino</label>
            <select class="form-select" id="cambio-cama-id-cama" required>
                <option value="">— Elegir —</option>
                <?php foreach ($camas as $c): ?>
                    <option value="<?= Html::encode((string) ($c['value'] ?? '')) ?>">
                        <?= Html::encode((string) ($c['label'] ?? '')) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="mb-3">
            <label class="form-label" for="cambio-cama-motivo">Motivo</label>
            <input type="text" class="form-control" id="cambio-cama-motivo" maxlength="128" required>
        </div>
        <button type="button" class="btn btn-info" id="cambio-cama-api-submit">Confirmar cambio</button>
    </div>
</div>
