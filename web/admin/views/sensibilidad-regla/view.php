<?php

use yii\helpers\Html;
use yii\widgets\DetailView;

/* @var $this yii\web\View */
/* @var $model common\models\SensibilidadRegla */

$this->title = 'Regla: ' . ($model->categoria ? $model->categoria->nombre : $model->id_categoria);
$this->params['breadcrumbs'][] = ['label' => 'Reglas de sensibilidad', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="sensibilidad-regla-view">

    <h1><?= Html::encode($this->title) ?></h1>

    <p>
        <?= Html::a('Editar', ['update', 'id' => $model->id], ['class' => 'btn btn-primary']) ?>
        <?= Html::a('Categorías', ['sensibilidad-categoria/index'], ['class' => 'btn btn-outline-secondary']) ?>
    </p>

    <?= DetailView::widget([
        'model' => $model,
        'attributes' => [
            'id',
            [
                'attribute' => 'id_categoria',
                'value' => $model->categoria ? $model->categoria->nombre : $model->id_categoria,
            ],
            'accion',
            'codigo_generalizacion',
            'etiqueta_generalizacion',
            [
                'label' => 'Servicios que reciben esta acción',
                'format' => 'raw',
                'value' => function () use ($model) {
                    $items = [];
                    foreach ($model->reglaServicios as $rs) {
                        if ($rs->servicio) {
                            $items[] = $rs->servicio->nombre;
                        }
                    }
                    return count($items) ? implode(', ', $items) : 'Ninguno (todos ven el dato completo).';
                },
            ],
        ],
    ]) ?>

</div>
