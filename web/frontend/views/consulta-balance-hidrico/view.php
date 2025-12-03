<?php

use yii\helpers\Html;
use yii\widgets\DetailView;

/* @var $this yii\web\View */
/* @var $model common\models\SegNivelInternacionBalancehidrico */

$this->title = $model->id;
$this->params['breadcrumbs'][] = ['label' => 'Seg Nivel Internacion Balancehidricos', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
\yii\web\YiiAsset::register($this);
?>
<div class="seg-nivel-internacion-balancehidrico-view">

  <div class="card">
    <div class="card-header bg-soft-info">
        <h1><?= Html::encode($this->title) ?></h1>
    </div>
    <div class="card-body">
      <p>
          <?php // Html::a('Update', ['update', 'id' => $model->id], ['class' => 'btn btn-primary']) ?>
          <?php /* Html::a('Delete', ['delete', 'id' => $model->id], [
              'class' => 'btn btn-danger',
              'data' => [
                  'confirm' => 'Are you sure you want to delete this item?',
                  'method' => 'post',
              ],
          ]) */?>
      </p>

      <?= DetailView::widget([
          'model' => $model,
          'attributes' => [
              'id',
              'id_internacion',
              'fecha',
              'tipo_ingreso',
              'cod_ingreso',
              'cod_egreso',
              'hora_inicio',
              'hora_fin',
              'cantidad',
          ],
      ]) ?>
    </div>
  </div>
</div>
