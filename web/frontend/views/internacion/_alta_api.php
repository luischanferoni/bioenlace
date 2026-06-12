<?php

use yii\helpers\Html;
use yii\helpers\Url;

/** @var common\models\SegNivelInternacion $model */
/** @var array<string, mixed> $ctx */

$tipos = $ctx['tipos_alta'] ?? [];
$plantillas = $ctx['plantillas'] ?? [];
$responsable = (string) ($ctx['responsable_nombre'] ?? '');
?>
<div id="internacion-alta-api"
     class="card border-primary mb-3"
     data-internacion-id="<?= (int) $model->id ?>"
     data-redirect-url="<?= Html::encode(Url::to(['site/index'])) ?>">
    <div class="card-header bg-soft-primary">
        <strong>Alta hospitalaria (epicrisis + checklist)</strong>
    </div>
    <div class="card-body">
        <?php if ($responsable !== ''): ?>
            <p class="text-muted mb-2">Responsable: <?= Html::encode($responsable) ?></p>
        <?php endif; ?>
        <div class="row g-2 mb-2">
            <div class="col-md-4">
                <label class="form-label">Fecha alta</label>
                <input type="date" class="form-control" id="alta-fecha-fin" value="<?= date('Y-m-d') ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">Hora</label>
                <input type="time" class="form-control" id="alta-hora-fin" value="<?= date('H:i') ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label">Tipo de alta</label>
                <select class="form-select" id="alta-tipo-alta" required>
                    <option value="">— Elegir —</option>
                    <?php foreach ($tipos as $t): ?>
                        <option value="<?= (int) $t['id'] ?>"><?= Html::encode((string) $t['label']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="mb-2">
            <label class="form-label">Plantilla de epicrisis</label>
            <select class="form-select" id="alta-plantilla-id">
                <option value="">— Sin plantilla —</option>
                <?php foreach ($plantillas as $p): ?>
                    <option value="<?= (int) $p['id'] ?>"><?= Html::encode((string) $p['nombre']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="mb-2">
            <label class="form-label">Epicrisis</label>
            <textarea class="form-control" id="alta-epicrisis" rows="8" required></textarea>
        </div>
        <div class="form-check mb-1">
            <input class="form-check-input" type="checkbox" id="alta-chk-med">
            <label class="form-check-label" for="alta-chk-med">Medicación e indicaciones entregadas</label>
        </div>
        <div class="form-check mb-1">
            <input class="form-check-input" type="checkbox" id="alta-chk-ind">
            <label class="form-check-label" for="alta-chk-ind">Indicaciones explicadas al paciente/familiar</label>
        </div>
        <div class="form-check mb-3">
            <input class="form-check-input" type="checkbox" id="alta-chk-ped">
            <label class="form-check-label" for="alta-chk-ped">Pedidos pendientes resueltos o planificados</label>
        </div>
        <button type="button" class="btn btn-primary" id="alta-api-submit">Registrar alta</button>
        <p class="small text-muted mt-2 mb-0">También podés usar el formulario clásico (modal Externación).</p>
    </div>
</div>
