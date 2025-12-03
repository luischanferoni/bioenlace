<?php

use yii\helpers\Html;

/* @var $this yii\web\View */
/* @var $model common\models\InfraestructuraCama */

$this->title = 'Actualizar Cama: ' . $model->id;
$this->params['breadcrumbs'][] = ['label' => 'Camas', 'url' => ['index']];
$this->params['breadcrumbs'][] = ['label' => $model->id, 'url' => ['view', 'id' => $model->id]];
$this->params['breadcrumbs'][] = 'Actualizar';
?>
<div class="infraestructura-cama-update">
    <div class="row">
        <div class="col-sm-2"></div>
        <div class="col-sm">

            <div class="card">
                <div class="card-header bg-soft-info">
                    <h2><?= Html::encode($this->title) ?></h2>
                </div>
            </div>
        </div>
        <div class="col-sm-2"></div>
    </div>

    <?= $this->render('_form', [
        'model' => $model,
    ]) ?>

</div>