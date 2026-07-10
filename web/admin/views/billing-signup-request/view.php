<?php

use common\models\BillingAccount;
use common\models\BillingSignupRequest;
use yii\helpers\ArrayHelper;
use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var BillingSignupRequest $model */
/** @var BillingAccount[] $ministerios */

$this->title = 'Solicitud #' . $model->id;
$this->params['breadcrumbs'][] = ['label' => 'Solicitudes', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="billing-signup-request-view">
    <h1><?= Html::encode($this->title) ?></h1>

    <dl class="dl-horizontal">
        <dt>Tipo</dt><dd><?= Html::encode($model->tipo) ?></dd>
        <dt>Estado</dt><dd><?= Html::encode($model->status) ?></dd>
        <dt>Organización</dt><dd><?= Html::encode($model->nombre_organizacion) ?></dd>
        <dt>Sector</dt><dd><?= Html::encode((string) $model->sector) ?></dd>
        <dt>Contacto</dt>
        <dd><?= Html::encode($model->contacto_nombre . ' ' . $model->contacto_apellido) ?>
            &lt;<?= Html::encode($model->contacto_email) ?>&gt;
            <?= Html::encode((string) $model->contacto_telefono) ?>
        </dd>
        <dt>Documento</dt><dd><?= Html::encode((string) $model->contacto_documento) ?></dd>
        <dt>Ministerio ref.</dt><dd><?= (int) $model->id_billing_account_ministerio ?></dd>
        <dt>Efector</dt><dd><?= (int) $model->id_efector ?></dd>
        <dt>Cuenta</dt><dd><?= (int) $model->id_billing_account ?></dd>
        <dt>Usuario</dt><dd><?= (int) $model->id_user ?></dd>
        <dt>Notas</dt><dd><pre><?= Html::encode((string) $model->notas) ?></pre></dd>
    </dl>

    <?php if ($model->status === BillingSignupRequest::STATUS_PENDING): ?>
        <?php if ($model->tipo === BillingSignupRequest::TIPO_MINISTERIO): ?>
            <h3>Aprobar ministerio</h3>
            <?= Html::beginForm(['approve', 'id' => $model->id], 'post') ?>
                <div class="form-group">
                    <label>Usar cuenta existente (opcional)</label>
                    <?= Html::dropDownList(
                        'id_billing_account',
                        null,
                        ['' => '— Crear cuenta nueva —'] + ArrayHelper::map($ministerios, 'id', 'nombre'),
                        ['class' => 'form-control']
                    ) ?>
                </div>
                <?= Html::submitButton('Aprobar', ['class' => 'btn btn-success']) ?>
            <?= Html::endForm() ?>
            <hr>
            <?= Html::beginForm(['reject', 'id' => $model->id], 'post') ?>
                <div class="form-group">
                    <label>Motivo rechazo</label>
                    <?= Html::textarea('notas', '', ['class' => 'form-control', 'rows' => 2]) ?>
                </div>
                <?= Html::submitButton('Rechazar', ['class' => 'btn btn-danger']) ?>
            <?= Html::endForm() ?>
        <?php elseif ($model->tipo === BillingSignupRequest::TIPO_EFECTOR && (int) $model->id_billing_account_ministerio > 0): ?>
            <h3>Aprobar cobertura / pool ministerial</h3>
            <?= Html::beginForm(['approve-pool-move', 'id' => $model->id], 'post') ?>
                <?= Html::submitButton('Mover POOL al ministerio', [
                    'class' => 'btn btn-warning',
                    'data-confirm' => '¿Confirmar movimiento de cupo al ministerio?',
                ]) ?>
            <?= Html::endForm() ?>
            <hr>
            <?= Html::beginForm(['reject', 'id' => $model->id], 'post') ?>
                <?= Html::textarea('notas', '', ['class' => 'form-control', 'rows' => 2, 'placeholder' => 'Motivo']) ?>
                <?= Html::submitButton('Rechazar', ['class' => 'btn btn-danger', 'style' => 'margin-top:8px']) ?>
            <?= Html::endForm() ?>
        <?php endif; ?>
    <?php endif; ?>
</div>
