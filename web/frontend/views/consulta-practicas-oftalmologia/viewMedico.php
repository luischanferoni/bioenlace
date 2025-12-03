<?php

use yii\helpers\Html;
use yii\widgets\DetailView;

/* @var $this yii\web\View */
/* @var $model common\models\ConsultaPracticasOftalmologia */

$this->title = $model->id;
$this->params['breadcrumbs'][] = ['label' => 'Consulta Practicas Oftalmologias', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
\yii\web\YiiAsset::register($this);
?>
<div class="consulta-practicas-oftalmologia-view">
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
                    <?= Html::a('Update', ['update-medico', 'id' => $model->id], ['class' => 'btn btn-primary']) ?>
                    <?= Html::a('Delete', ['delete-medico', 'id' => $model->id], [
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
                        'id_consulta',
                        'codigo',
                        'ojo',
                        'prueba',
                        #'estado',
                        'resultado:ntext',
                        #'informe:ntext',
                        #'adjunto:ntext',
                    ],
                ]) ?>
            </dl>
        </div>
</div>
