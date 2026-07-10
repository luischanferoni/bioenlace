<?php

use common\models\BillingAccount;
use common\models\BillingAccountEfector;
use yii\helpers\Html;

/* @var $this yii\web\View */
/* @var $model common\models\BillingAccount */
/* @var $summary array */
/* @var $members common\models\BillingAccountEfector[] */
/* @var $sellableClasses string[] */
/* @var $classLabels array */

$this->title = $model->nombre;
$this->params['breadcrumbs'][] = ['label' => 'Licencias / Contratos', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;

$byCode = [];
foreach ($summary as $row) {
    $byCode[$row['code']] = $row;
}
$rolOptions = BillingAccountEfector::rolOptions();
?>
<div class="billing-account-view">
    <div class="card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h1 class="mb-0"><?= Html::encode($this->title) ?></h1>
            <div>
                <?= Html::a('Editar', ['update', 'id' => $model->id], ['class' => 'btn btn-primary']) ?>
                <?= Html::a('Volver', ['index'], ['class' => 'btn btn-secondary']) ?>
            </div>
        </div>
        <div class="card-body">
            <p>
                <strong>Tipo:</strong> <?= Html::encode(BillingAccount::tipoOptions()[$model->tipo] ?? $model->tipo) ?>
                · <strong>Estado:</strong> <?= (int) $model->activo === 1 ? 'Activo' : 'Inactivo' ?>
            </p>
            <?php if ($model->notas): ?>
                <p class="text-muted"><?= Html::encode($model->notas) ?></p>
            <?php endif; ?>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header"><h2 class="h5 mb-0">Clases contratadas (pool)</h2></div>
        <div class="card-body">
            <p class="text-muted small">
                El cupo lo consumen solo los efectores con rol <strong>Pool</strong>.
                Los <strong>Afiliados</strong> pertenecen a la cuenta (p. ej. ministerio) pero no usan este cupo.
            </p>
            <table class="table table-bordered table-sm">
                <thead>
                <tr>
                    <th>Clase</th>
                    <th>Máx. profesionales</th>
                    <th>Uso actual</th>
                    <th>Pending</th>
                    <th>Dictado</th>
                    <th>Videollamada</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($sellableClasses as $code): ?>
                    <?php
                    $row = $byCode[$code] ?? null;
                    $label = $classLabels[$code] ?? $code;
                    ?>
                    <tr>
                        <td colspan="7" class="bg-light">
                            <strong><?= Html::encode($label) ?> (<?= Html::encode($code) ?>)</strong>
                            <?php if ($row): ?>
                                <?= Html::beginForm(['deactivate-entitlement', 'id' => $model->id, 'encounter_class' => $code], 'post', ['class' => 'd-inline float-end']) ?>
                                <?= Html::submitButton('Quitar clase', [
                                    'class' => 'btn btn-sm btn-outline-danger',
                                    'data-confirm' => '¿Quitar esta clase del contrato?',
                                ]) ?>
                                <?= Html::endForm() ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <td colspan="7">
                            <?= Html::beginForm(['save-entitlement', 'id' => $model->id], 'post', ['class' => 'row g-2 align-items-end']) ?>
                            <?= Html::hiddenInput('encounter_class', $code) ?>
                            <div class="col-md-2">
                                <label class="form-label">Máx. PES</label>
                                <?= Html::textInput('max_pes', $row['max_pes'] ?? '', ['class' => 'form-control form-control-sm', 'type' => 'number', 'min' => 0]) ?>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Uso</label>
                                <div class="form-control-plaintext form-control-sm"><?= (int) ($row['used'] ?? 0) ?></div>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Pending</label>
                                <div class="form-control-plaintext form-control-sm">
                                    <?php if ($row && $row['pending_max_pes'] !== null): ?>
                                        <?= (int) $row['pending_max_pes'] ?> desde <?= Html::encode($row['pending_effective_on'] ?? '') ?>
                                    <?php else: ?>
                                        —
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label"><?= Html::checkbox('dictado_incluido', !empty($row['dictado_incluido']), ['label' => 'Dictado incluido']) ?></label>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label"><?= Html::checkbox('videollamada_permitida', !empty($row['videollamada_permitida']), ['label' => 'Videollamada']) ?></label>
                            </div>
                            <div class="col-md-1">
                                <?= Html::submitButton($row ? 'Guardar' : 'Agregar', ['class' => 'btn btn-sm btn-primary']) ?>
                            </div>
                            <?= Html::endForm() ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h2 class="h5 mb-0">Efectores miembros</h2></div>
        <div class="card-body">
            <table class="table table-sm">
                <thead>
                <tr><th>ID</th><th>Nombre</th><th>Rol</th><th></th></tr>
                </thead>
                <tbody>
                <?php foreach ($members as $m): ?>
                    <?php $rol = (string) ($m->rol_membresia ?: BillingAccountEfector::ROL_POOL); ?>
                    <tr>
                        <td><?= (int) $m->id_efector ?></td>
                        <td>
                            <?php if ($m->efector): ?>
                                <?= Html::a(Html::encode($m->efector->nombre), ['/efectores/view', 'id' => $m->id_efector]) ?>
                            <?php else: ?>
                                #<?= (int) $m->id_efector ?>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?= Html::beginForm(
                                ['update-membership-role', 'id' => $model->id, 'id_efector' => $m->id_efector],
                                'post',
                                ['class' => 'd-flex gap-1 align-items-center']
                            ) ?>
                            <?= Html::dropDownList('rol_membresia', $rol, $rolOptions, [
                                'class' => 'form-select form-select-sm',
                                'style' => 'min-width: 11rem',
                            ]) ?>
                            <?= Html::submitButton('Cambiar', ['class' => 'btn btn-sm btn-outline-secondary']) ?>
                            <?= Html::endForm() ?>
                        </td>
                        <td>
                            <?= Html::beginForm(['detach-efector', 'id' => $model->id, 'id_efector' => $m->id_efector], 'post') ?>
                            <?= Html::submitButton('Quitar', [
                                'class' => 'btn btn-sm btn-outline-danger',
                                'data-confirm' => '¿Desasociar este efector?',
                            ]) ?>
                            <?= Html::endForm() ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($members === []): ?>
                    <tr><td colspan="4" class="text-muted">Sin efectores asociados.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>

            <?= Html::beginForm(['attach-efector', 'id' => $model->id], 'post', ['class' => 'row g-2 align-items-end']) ?>
            <div class="col-md-4">
                <label class="form-label">Agregar efector (ID)</label>
                <?= Html::textInput('id_efector', '', [
                    'class' => 'form-control',
                    'type' => 'number',
                    'min' => 1,
                    'required' => true,
                    'placeholder' => 'id_efector',
                ]) ?>
            </div>
            <div class="col-md-4">
                <label class="form-label">Rol</label>
                <?= Html::dropDownList('rol_membresia', BillingAccountEfector::ROL_POOL, $rolOptions, [
                    'class' => 'form-select',
                ]) ?>
                <div class="form-text">
                    Pool: consume cupo (máx. una cuenta pool por efector).
                    Afiliado: solo jerarquía; puede tener pool en otra cuenta.
                </div>
            </div>
            <div class="col-md-2">
                <?= Html::submitButton('Asociar', ['class' => 'btn btn-success']) ?>
            </div>
            <?= Html::endForm() ?>
        </div>
    </div>
</div>
