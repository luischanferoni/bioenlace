<?php

use yii\helpers\Html;

/** @var common\models\Person\Persona $persona */
/** @var array<string, mixed> $ctx */

$profesionales = $ctx['profesionales'] ?? [];
$camas = $ctx['camas_disponibles'] ?? [];
$coberturas = $ctx['coberturas'] ?? [];
$efectores = $ctx['efectores_origen'] ?? [];
$tiposIngreso = $ctx['tipos_ingreso'] ?? [];
$ingresaEn = $ctx['ingresa_en'] ?? [];
$ingresaCon = $ctx['ingresa_con'] ?? [];
$idCama = (int) ($ctx['id_cama'] ?? 0);
$idGuardia = (int) ($ctx['id_guardia'] ?? 0);
?>
<div id="internacion-ingreso-api"
     class="card border-success mb-3"
     data-id-persona="<?= (int) $persona->id_persona ?>"
     data-id-guardia="<?= $idGuardia ?>">
    <div class="card-header bg-soft-success">
        <strong>Ingreso a internación</strong>
    </div>
    <div class="card-body">
        <p class="mb-2">
            <strong><?= Html::encode((string) ($ctx['paciente_nombre'] ?? '')) ?></strong>
            <?php if (!empty($ctx['paciente_documento'])): ?>
                <span class="text-muted">— <?= Html::encode((string) $ctx['paciente_documento']) ?></span>
            <?php endif; ?>
        </p>
        <?php if (!empty($ctx['cama_label'])): ?>
            <p class="text-muted mb-3">Cama: <?= Html::encode((string) $ctx['cama_label']) ?></p>
        <?php endif; ?>

        <div class="mb-2">
            <label class="form-label" for="ingreso-id-cama">Cama</label>
            <select class="form-select" id="ingreso-id-cama" required <?= $idCama > 0 ? 'disabled' : '' ?>>
                <?php if ($idCama <= 0): ?>
                    <option value="">— Elegir —</option>
                <?php endif; ?>
                <?php foreach ($camas as $c): ?>
                    <option value="<?= Html::encode((string) ($c['value'] ?? '')) ?>"
                        <?= (int) ($c['value'] ?? 0) === $idCama ? 'selected' : '' ?>>
                        <?= Html::encode((string) ($c['label'] ?? '')) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?php if ($idCama > 0): ?>
                <input type="hidden" id="ingreso-id-cama-hidden" value="<?= $idCama ?>">
            <?php endif; ?>
        </div>

        <div class="mb-2">
            <label class="form-label" for="ingreso-id-pes">Profesional</label>
            <select class="form-select" id="ingreso-id-pes" required>
                <option value="">— Elegir —</option>
                <?php foreach ($profesionales as $p): ?>
                    <option value="<?= Html::encode((string) ($p['value'] ?? '')) ?>">
                        <?= Html::encode((string) ($p['label'] ?? '')) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="row mb-2">
            <div class="col-md-6">
                <label class="form-label" for="ingreso-fecha-inicio">Fecha ingreso</label>
                <input type="date" class="form-control" id="ingreso-fecha-inicio" required
                       value="<?= Html::encode((string) ($ctx['fecha_inicio'] ?? date('Y-m-d'))) ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label" for="ingreso-hora-inicio">Hora</label>
                <input type="time" class="form-control" id="ingreso-hora-inicio" required
                       value="<?= Html::encode((string) ($ctx['hora_inicio'] ?? date('H:i'))) ?>">
            </div>
        </div>

        <div class="mb-2">
            <label class="form-label" for="ingreso-id-tipo-ingreso">Tipo de ingreso</label>
            <select class="form-select" id="ingreso-id-tipo-ingreso" required>
                <option value="">— Elegir —</option>
                <?php foreach ($tiposIngreso as $t): ?>
                    <option value="<?= Html::encode((string) ($t['value'] ?? '')) ?>"
                        <?= (string) ($t['value'] ?? '') === (string) ($ctx['id_tipo_ingreso_default'] ?? '') ? 'selected' : '' ?>>
                        <?= Html::encode((string) ($t['label'] ?? '')) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="mb-2">
            <label class="form-label" for="ingreso-id-efector-origen">Efector origen (si derivación)</label>
            <select class="form-select" id="ingreso-id-efector-origen">
                <option value="">—</option>
                <?php foreach ($efectores as $e): ?>
                    <option value="<?= Html::encode((string) ($e['value'] ?? '')) ?>">
                        <?= Html::encode((string) ($e['label'] ?? '')) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="row mb-2">
            <div class="col-md-6">
                <label class="form-label" for="ingreso-ingresa-en">Ingresa en</label>
                <select class="form-select" id="ingreso-ingresa-en" required>
                    <option value="">—</option>
                    <?php foreach ($ingresaEn as $o): ?>
                        <option value="<?= Html::encode((string) ($o['value'] ?? '')) ?>">
                            <?= Html::encode((string) ($o['label'] ?? '')) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="ingreso-ingresa-con">Ingresa con</label>
                <select class="form-select" id="ingreso-ingresa-con" required>
                    <option value="">—</option>
                    <?php foreach ($ingresaCon as $o): ?>
                        <option value="<?= Html::encode((string) ($o['value'] ?? '')) ?>">
                            <?= Html::encode((string) ($o['label'] ?? '')) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="mb-2">
            <label class="form-label" for="ingreso-contacto-nombre">Nombre acompañante</label>
            <input type="text" class="form-control" id="ingreso-contacto-nombre" maxlength="128">
        </div>
        <div class="mb-2">
            <label class="form-label" for="ingreso-contacto-tel">Teléfono acompañante</label>
            <input type="text" class="form-control" id="ingreso-contacto-tel" maxlength="32">
        </div>
        <div class="mb-2">
            <label class="form-label" for="ingreso-situacion">Situación al ingresar</label>
            <textarea class="form-control" id="ingreso-situacion" rows="3"></textarea>
        </div>
        <div class="mb-3">
            <label class="form-label" for="ingreso-obra-social">Obra social</label>
            <select class="form-select" id="ingreso-obra-social">
                <option value="">—</option>
                <?php foreach ($coberturas as $c): ?>
                    <option value="<?= Html::encode((string) ($c['value'] ?? '')) ?>"
                        <?= (string) ($c['value'] ?? '') === (string) ($ctx['obra_social_default'] ?? '') ? 'selected' : '' ?>>
                        <?= Html::encode((string) ($c['label'] ?? '')) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php if (!empty($ctx['condiciones_derivacion'])): ?>
            <input type="hidden" id="ingreso-condiciones-derivacion"
                   value="<?= Html::encode((string) $ctx['condiciones_derivacion']) ?>">
        <?php else: ?>
            <div class="mb-3">
                <label class="form-label" for="ingreso-condiciones-derivacion">Condiciones derivación</label>
                <textarea class="form-control" id="ingreso-condiciones-derivacion" rows="2"></textarea>
            </div>
        <?php endif; ?>

        <button type="button" class="btn btn-success" id="ingreso-api-submit">Confirmar ingreso</button>
    </div>
</div>
