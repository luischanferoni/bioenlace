<?php

use common\models\BillingAccount;
use yii\grid\GridView;
use yii\helpers\Html;

/* @var $this yii\web\View */
/* @var $searchModel common\models\busquedas\BillingAccountBusqueda */
/* @var $dataProvider yii\data\ActiveDataProvider */

$this->title = 'Licencias / Contratos';
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="billing-account-index">
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h1 class="mb-0"><?= Html::encode($this->title) ?></h1>
            <?= Html::a('Nueva cuenta', ['create'], ['class' => 'btn btn-success']) ?>
        </div>
        <div class="card-body">
            <?= GridView::widget([
                'dataProvider' => $dataProvider,
                'filterModel' => $searchModel,
                'columns' => [
                    ['class' => 'yii\grid\SerialColumn'],
                    'id',
                    'nombre',
                    [
                        'attribute' => 'tipo',
                        'filter' => BillingAccount::tipoOptions(),
                        'value' => static function (BillingAccount $m) {
                            return BillingAccount::tipoOptions()[$m->tipo] ?? $m->tipo;
                        },
                    ],
                    [
                        'label' => 'Efectores',
                        'value' => static function (BillingAccount $m) {
                            return (int) $m->getMembers()->count();
                        },
                    ],
                    [
                        'attribute' => 'activo',
                        'filter' => [1 => 'Activo', 0 => 'Inactivo'],
                        'value' => static function (BillingAccount $m) {
                            return (int) $m->activo === 1 ? 'Activo' : 'Inactivo';
                        },
                    ],
                    [
                        'class' => 'yii\grid\ActionColumn',
                        'template' => '{view} {update} {delete}',
                    ],
                ],
            ]) ?>
        </div>
    </div>
</div>
