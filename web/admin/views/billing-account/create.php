<?php

use yii\helpers\Html;

/* @var $this yii\web\View */
/* @var $model common\models\BillingAccount */

$this->title = 'Nueva cuenta de licencia';
$this->params['breadcrumbs'][] = ['label' => 'Licencias / Contratos', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="billing-account-create card">
    <div class="card-header"><h1><?= Html::encode($this->title) ?></h1></div>
    <div class="card-body">
        <?= $this->render('_form', ['model' => $model]) ?>
    </div>
</div>
