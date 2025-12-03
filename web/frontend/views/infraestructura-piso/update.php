<?php

use yii\helpers\Html;

/* @var $this yii\web\View */
/* @var $model common\models\InfraestructuraPiso */

$this->title = 'Actualizar Piso: ' . $model->id;
$this->params['breadcrumbs'][] = ['label' => 'Pisos', 'url' => ['index']];
$this->params['breadcrumbs'][] = ['label' => $model->id, 'url' => ['view', 'id' => $model->id]];
$this->params['breadcrumbs'][] = 'Actualizar';
?>
<div class="infraestructura-piso-update">

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