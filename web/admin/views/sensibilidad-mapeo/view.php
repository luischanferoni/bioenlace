<?php

use yii\helpers\Html;
use yii\widgets\DetailView;

/* @var $this yii\web\View */
/* @var $model common\models\SensibilidadMapeoSnomed */

$this->title = $model->codigo . ' → ' . ($model->categoria ? $model->categoria->nombre : '');
$this->params['breadcrumbs'][] = ['label' => 'Mapeo SNOMED', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="sensibilidad-mapeo-view">

    <h1><?= Html::encode($this->title) ?></h1>

    <p>
        <?= Html::a('Actualizar', ['update', 'id' => $model->id], ['class' => 'btn btn-primary']) ?>
        <?= Html::a('Eliminar', ['delete', 'id' => $model->id], [
            'class' => 'btn btn-danger',
            'data' => [
                'confirm' => '¿Eliminar este mapeo?',
                'method' => 'post',
            ],
        ]) ?>
    </p>

    <?= DetailView::widget([
        'model' => $model,
        'attributes' => [
            'id',
            [
                'attribute' => 'tabla_snomed',
                'value' => \common\models\SensibilidadMapeoSnomed::TABLAS[$model->tabla_snomed] ?? $model->tabla_snomed,
            ],
            'codigo',
            [
                'label' => 'Término SNOMED',
                'value' => $model->getTerminoSnomed(),
            ],
            [
                'attribute' => 'id_categoria',
                'value' => $model->categoria ? $model->categoria->nombre : '',
            ],
        ],
    ]) ?>

</div>
