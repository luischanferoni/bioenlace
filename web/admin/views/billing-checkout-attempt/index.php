<?php

use common\models\Organization\BillingCheckoutAttempt;
use yii\grid\GridView;
use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var common\models\Organization\BillingCheckoutAttemptBusqueda $searchModel */
/** @var yii\data\ActiveDataProvider $dataProvider */

$this->title = 'Checkouts de suscripción';
$this->params['breadcrumbs'][] = ['label' => 'Licencias / Contratos', 'url' => ['/billing-account/index']];
$this->params['breadcrumbs'][] = $this->title;
$statusOptions = BillingCheckoutAttempt::statusOptions();
?>
<div class="billing-checkout-attempt-index">
    <div class="card">
        <div class="card-header">
            <h1 class="mb-0"><?= Html::encode($this->title) ?></h1>
        </div>
        <div class="card-body">
            <?= GridView::widget([
                'dataProvider' => $dataProvider,
                'filterModel' => $searchModel,
                'columns' => [
                    'id',
                    [
                        'attribute' => 'created_at',
                        'label' => 'Creado',
                        'format' => 'datetime',
                    ],
                    [
                        'attribute' => 'status',
                        'filter' => $statusOptions,
                        'value' => static function (BillingCheckoutAttempt $m) use ($statusOptions) {
                            return $statusOptions[$m->status] ?? $m->status;
                        },
                    ],
                    'sector',
                    'payer_email:email',
                    [
                        'label' => 'Efector',
                        'filter' => Html::activeTextInput($searchModel, 'id_efector', [
                            'class' => 'form-control',
                            'placeholder' => 'ID efector',
                        ]),
                        'value' => static function (BillingCheckoutAttempt $m) {
                            return $m->efector->nombre ?? $m->reserved_sisa_code ?? '—';
                        },
                    ],
                    [
                        'attribute' => 'amount_total_ars',
                        'label' => 'Total ARS',
                        'format' => ['decimal', 2],
                    ],
                    [
                        'attribute' => 'paid_through_at',
                        'label' => 'Pago vigente hasta',
                        'format' => 'datetime',
                    ],
                    [
                        'attribute' => 'provider_subscription_status',
                        'label' => 'Estado MP',
                    ],
                    [
                        'class' => 'yii\grid\ActionColumn',
                        'template' => '{view}',
                    ],
                ],
            ]) ?>
        </div>
    </div>
</div>
