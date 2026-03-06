<?php

use yii\helpers\Html;
use yii\grid\GridView;

/* @var $this yii\web\View */
/* @var $dataProvider yii\data\ActiveDataProvider */

$this->title = 'Reglas de sensibilidad';
$this->params['breadcrumbs'][] = ['label' => 'Resumen IA', 'url' => ['#']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="sensibilidad-regla-index">

    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <div class="header-title d-flex align-items-self justify-content-between">
                    <h2 class="card-title mt-1"><?= Html::encode($this->title) ?></h2>
                </div>
            </div>
            <div class="card-body">
                <p class="text-muted small">Por categoría: acción (generalizar/ocultar) y lista de servicios que la reciben; el resto ve el dato completo. Lista vacía = todos ven completo.</p>
                <?= GridView::widget([
                    'dataProvider' => $dataProvider,
                    'columns' => [
                        'id',
                        [
                            'attribute' => 'id_categoria',
                            'value' => function ($model) {
                                return $model->categoria ? $model->categoria->nombre : $model->id_categoria;
                            },
                        ],
                        'accion',
                        [
                            'label' => 'Servicios (generalizar/ocultar)',
                            'format' => 'raw',
                            'value' => function ($model) {
                                $nombres = [];
                                foreach ($model->reglaServicios as $rs) {
                                    if ($rs->servicio) {
                                        $nombres[] = $rs->servicio->nombre;
                                    }
                                }
                                return count($nombres) ? implode(', ', $nombres) : '<em>Ninguno (todos ven completo)</em>';
                            },
                        ],
                        ['class' => 'yii\grid\ActionColumn'],
                    ],
                ]); ?>
            </div>
        </div>
    </div>
</div>
