<?php

use common\models\BillingSignupRequest;
use yii\grid\GridView;
use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var yii\data\ActiveDataProvider $dataProvider */

$this->title = 'Solicitudes de alta / licencia';
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="billing-signup-request-index">
    <h1><?= Html::encode($this->title) ?></h1>
    <?= GridView::widget([
        'dataProvider' => $dataProvider,
        'columns' => [
            'id',
            'tipo',
            'status',
            'nombre_organizacion',
            'sector',
            'contacto_email',
            'created_at',
            [
                'class' => 'yii\grid\ActionColumn',
                'template' => '{view}',
            ],
        ],
    ]) ?>
</div>
