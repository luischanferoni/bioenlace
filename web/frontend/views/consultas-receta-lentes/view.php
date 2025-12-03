<?php

use yii\helpers\Html;
use yii\widgets\DetailView;

/* @var $this yii\web\View */
/* @var $model common\models\ConsultasRecetaLentes */

$this->title = $model->id;
$this->params['breadcrumbs'][] = ['label' => 'Consultas Receta Lentes', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
\yii\web\YiiAsset::register($this);
?>
<div class="consultas-receta-lentes-view">
    <div class="card">
        <div class="card-header">
            <div class="header-title">
                <div class="col-lg-8 col-xs-12">
                    <h1>Crear Consulta Oftalmológica</h1>
                </div>
            </div>
        </div>
        <div class="card-body">
            <dl class="row">
                <p>
                    <?= Html::a('Update', ['update', 'id' => $model->id], ['class' => 'btn btn-primary']) ?>
                    <?= Html::a('Delete', ['delete', 'id' => $model->id], [
                        'class' => 'btn btn-danger',
                        'data' => [
                            'confirm' => 'Are you sure you want to delete this item?',
                            'method' => 'post',
                        ],
                    ]) ?>
                </p>
            </dl>
            <dl class="row">
                <?= DetailView::widget([
                    'model' => $model,
                    'attributes' => [
                        'id',
                        'oi_esfera',
                        'od_esfera',
                        'oi_cilindro',
                        'od_cilindro',
                        'oi_eje',
                        'od_eje',
                    ],
                ]) ?>
            </dl>
        </div>
    </div>
</div>
