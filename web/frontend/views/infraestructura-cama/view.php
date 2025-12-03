<?php

use yii\helpers\Html;
use yii\widgets\DetailView;

/* @var $this yii\web\View */
/* @var $model common\models\InfraestructuraCama */

$this->title = 'Cama ' . $model->id;
$this->params['breadcrumbs'][] = ['label' => 'Infraestructura Camas', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
\yii\web\YiiAsset::register($this);
?>
<div class="infraestructura-cama-view">


    <div class="card">
        <div class="card-header bg-soft-info">
            <h2><?= Html::encode($this->title) ?></h2>
        </div>
    </div>

    <div class="card">
        <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-2 mb-2 pe-2">
            <?= Html::a('Actualizar', ['update', 'id' => $model->id], ['class' => 'btn btn-primary rounded-pill']) ?>
            <?= Html::a('Eliminar', ['delete', 'id' => $model->id], [
                'class' => 'btn btn-danger rounded-pill',
                'data' => [
                    'confirm' => '¿Está seguro de elimir este elemento?',
                    'method' => 'post',
                ],
            ]) ?>
        </div>

    </div>

    <div class="card">
        <div class="card-body">

            <?= DetailView::widget([
                'model' => $model,
                'attributes' => [
                    //'id',
                    'nro_cama',
                    'respirador',
                    'monitor',
                    [   //muestra el nombre de la sala seleccionado en el listado
                        'label' => 'Sala',
                        'attribute' => 'id_sala',
                        'value' => $model->sala->descripcion,
                    ],
                    'estado',
                ],
            ]) ?>
        </div>
    </div>

    <p>
        <?= Html::a('Volver', ['index'], ['class' => 'btn btn-success rounded-pill']) ?>
    </p>

</div>