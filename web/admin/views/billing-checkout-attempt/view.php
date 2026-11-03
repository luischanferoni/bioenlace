<?php

use common\models\Organization\BillingCheckoutAttempt;
use yii\helpers\Html;
use yii\helpers\Json;

/** @var yii\web\View $this */
/** @var BillingCheckoutAttempt $model */

$this->title = 'Checkout #' . $model->id;
$this->params['breadcrumbs'][] = ['label' => 'Licencias / Contratos', 'url' => ['/billing-account/index']];
$this->params['breadcrumbs'][] = ['label' => 'Checkouts', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;

$statusOptions = BillingCheckoutAttempt::statusOptions();
$formatAmount = static function ($value): string {
    return $value === null ? '—' : Yii::$app->formatter->asDecimal((float) $value, 2);
};
$formatDate = static function ($value): string {
    return empty($value) ? '—' : (string) $value;
};
$prettyJson = static function (?string $raw): string {
    $decoded = json_decode((string) $raw, true);
    if (!is_array($decoded)) {
        return (string) $raw;
    }

    return (string) Json::encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
};
?>
<div class="billing-checkout-attempt-view">
    <h1><?= Html::encode($this->title) ?></h1>

    <dl class="dl-horizontal">
        <dt>Estado</dt><dd><?= Html::encode($statusOptions[$model->status] ?? $model->status) ?></dd>
        <dt>Clave</dt><dd><code><?= Html::encode($model->checkout_key) ?></code></dd>
        <dt>Sector</dt><dd><?= Html::encode((string) $model->sector) ?></dd>
        <dt>Perfil</dt><dd><?= Html::encode((string) $model->perfil) ?></dd>
        <dt>Creado</dt><dd><?= Html::encode($formatDate($model->created_at)) ?></dd>
        <dt>Actualizado</dt><dd><?= Html::encode($formatDate($model->updated_at)) ?></dd>

        <dt>E-mail pagador</dt><dd><?= Html::encode((string) $model->payer_email) ?></dd>
        <dt>Usuario</dt><dd><?= (int) $model->id_user ?></dd>
        <dt>Persona</dt>
        <dd>
            <?= (int) $model->id_persona ?>
            <?php if ($model->persona !== null): ?>
                — <?= Html::encode(trim($model->persona->nombre . ' ' . $model->persona->apellido)) ?>
            <?php endif; ?>
        </dd>

        <dt>Efector</dt>
        <dd>
            <?php if ($model->efector !== null): ?>
                <?= Html::a(
                    Html::encode($model->efector->nombre),
                    ['/efectores/view', 'id' => (int) $model->efector->id_efector]
                ) ?>
                (SISA <?= Html::encode((string) $model->efector->codigo_sisa) ?>)
            <?php else: ?>
                <?= Html::encode((string) ($model->reserved_sisa_code ?? '—')) ?>
            <?php endif; ?>
        </dd>
        <dt>Ministerio</dt><dd><?= (int) $model->id_billing_account_ministerio ?></dd>
        <dt>Cuenta de licencia</dt>
        <dd>
            <?php if ((int) $model->id_billing_account > 0): ?>
                <?= Html::a(
                    'Cuenta #' . (int) $model->id_billing_account,
                    ['/billing-account/view', 'id' => (int) $model->id_billing_account]
                ) ?>
            <?php else: ?>
                —
            <?php endif; ?>
        </dd>
    </dl>

    <h3>Importes</h3>
    <dl class="dl-horizontal">
        <dt>USD</dt><dd><?= Html::encode($formatAmount($model->amount_usd)) ?></dd>
        <dt>Tasa FX</dt><dd><?= Html::encode($formatAmount($model->fx_rate)) ?> (<?= Html::encode($formatDate($model->fx_rate_effective_date)) ?>)</dd>
        <dt>Neto ARS</dt><dd><?= Html::encode($formatAmount($model->amount_net_ars)) ?></dd>
        <dt>IVA</dt><dd><?= Html::encode($formatAmount($model->vat_rate)) ?>% = <?= Html::encode($formatAmount($model->vat_ars)) ?></dd>
        <dt>Total ARS</dt><dd><strong><?= Html::encode($formatAmount($model->amount_total_ars)) ?></strong></dd>
        <dt>Pago vigente hasta</dt><dd><?= Html::encode($formatDate($model->paid_through_at)) ?></dd>
    </dl>

    <h3>Proveedor de pagos</h3>
    <dl class="dl-horizontal">
        <dt>Suscripción</dt><dd><?= Html::encode((string) ($model->provider_subscription_id ?? '—')) ?></dd>
        <dt>Estado suscripción</dt><dd><?= Html::encode((string) ($model->provider_subscription_status ?? '—')) ?></dd>
        <dt>Pago</dt><dd><?= Html::encode((string) ($model->provider_payment_id ?? '—')) ?></dd>
        <dt>Estado del pago</dt><dd><?= Html::encode((string) ($model->provider_status ?? '—')) ?></dd>
        <dt>URL de checkout</dt>
        <dd>
            <?php if (!empty($model->checkout_url)): ?>
                <?= Html::a(Html::encode($model->checkout_url), $model->checkout_url, ['target' => '_blank', 'rel' => 'noopener']) ?>
            <?php else: ?>
                —
            <?php endif; ?>
        </dd>
    </dl>

    <h3>Selección congelada</h3>
    <dl class="dl-horizontal">
        <dt>Plan</dt>
        <dd><pre><?= Html::encode($prettyJson($model->plan_json)) ?></pre></dd>
        <dt>Efector</dt>
        <dd><pre><?= Html::encode($prettyJson($model->efector_json)) ?></pre></dd>
    </dl>
</div>
