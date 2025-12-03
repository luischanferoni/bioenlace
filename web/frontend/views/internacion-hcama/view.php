<?php

use yii\helpers\Html;
use yii\widgets\DetailView;

/* @var $this yii\web\View */
/* @var $model common\models\SegNivelInternacionHcama */

$this->title = sprintf('Registro de cambio de cama %d', $model->id);
$this->params['breadcrumbs'][] = ['label' => 'Seg Nivel Internacion Hcamas', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
\yii\web\YiiAsset::register($this);
?>
<div class="seg-nivel-internacion-hcama-view">
  <div class="card">
    <div class="card-header bg-soft-info">
      <h1><?= Html::encode($this->title) ?></h1>
    </div>
    <div class="card-body">
    <?php
        /* echo Html::a('Update', ['update', 'id' => $model->id], ['class' => 'btn btn-primary']) ?>
        echo Html::a('Delete', ['delete', 'id' => $model->id], [
            'class' => 'btn btn-danger',
            'data' => [
                'confirm' => 'Are you sure you want to delete this item?',
                'method' => 'post',
            ],
        ]) */
    ?>

    <?= DetailView::widget([
        'model' => $model,
        'attributes' => [
            'id',
            'id_internacion',
            'id_cama',
            'fecha_ingreso',
            'motivo',
        ],
    ]) ?>
    </div>
  </div>
</div>
