<?php

use yii\helpers\Html;
use yii\grid\GridView;

/* @var $this yii\web\View */
/* @var $dataProvider yii\data\ActiveDataProvider */

$this->title = 'Internaciones / Historia de camas';
//$this->params['breadcrumbs'][] = '';
?>
<div class="seg-nivel-internacion-hcama-index">
  <div class="card">
    <div class="card-header bg-soft-info">
      <h1><?= Html::encode('Historia de camas para internación '.$context['id_internacion']) ?></h1>
    </div>
    <div class="card-body">
      <?php # Html::a('Create Seg Nivel Internacion Hcama', ['create'], ['class' => 'btn btn-success']) ?>
      <?= GridView::widget([
          'dataProvider' => $dataProvider,
          'columns' => [
              //['class' => 'yii\grid\SerialColumn'],
              'id',
              // 'id_internacion',
              [ 'label' => 'ID Cama',
                'value' => function ($data) {
                    return $data->id_cama;
                    },
              ],
              [ 'label' => 'Nro Cama',
                'value' => function ($data) {
                    return $data->cama->nro_cama;
                    },
              ],
              'fecha_ingreso',
              'motivo',

              //['class' => 'yii\grid\ActionColumn'],
          ],
      ]); ?>
    </div>
  </div>
</div>
