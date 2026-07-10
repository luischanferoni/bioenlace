<?php

use yii\helpers\Html;

/* @var $this yii\web\View */
/* @var $model common\models\BillingAccount */

$this->title = 'Editar cuenta: ' . $model->nombre;
$this->params['breadcrumbs'][] = ['label' => 'Licencias / Contratos', 'url' => ['index']];
$this->params['breadcrumbs'][] = ['label' => $model->nombre, 'url' => ['view', 'id' => $model->id]];
$this->params['breadcrumbs'][] = 'Editar';
?>
<div class="billing-account-update card">
    <div class="card-header"><h1><?= Html::encode($this->title) ?></h1></div>
    <div class="card-body">
        <?= $this->render('_form', ['model' => $model]) ?>
    </div>
</div>
