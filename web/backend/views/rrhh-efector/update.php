<?php

use yii\helpers\Html;

/* @var $this yii\web\View */
/* @var $model common\models\RrhhEfector */

$this->title = 'Update Rrhh Efector: ' . $model->id_rr_hh;
$this->params['breadcrumbs'][] = ['label' => 'Rrhh Efectors', 'url' => ['index']];
$this->params['breadcrumbs'][] = ['label' => $model->id_rr_hh, 'url' => ['view', 'id_rr_hh' => $model->id_rr_hh, 'id_efector' => $model->id_efector]];
$this->params['breadcrumbs'][] = 'Update';
?>
<div class="rrhh-efector-update">

    <h1><?= Html::encode($this->title) ?></h1>

    <?= $this->render('_form', [
        'model' => $model,
    ]) ?>

</div>
