<?php

use yii\helpers\Html;
use yii\widgets\DetailView;
use common\models\Persona;

/* @var $this yii\web\View */
/* @var $model common\models\InfraestructuraSala */

$this->title = 'Sala N° ' . $model->nro_sala;
$this->params['breadcrumbs'][] = ['label' => 'Infraestructura Salas', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
\yii\web\YiiAsset::register($this);
?>
<div class="infraestructura-sala-view">

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
                    'nro_sala',
                    'descripcion',
                    'covid',
                    //'id_responsable',
                    [   //muestra el nombre del responsable seleccionado en el listado
                        'label' => 'Responsable',
                        'attribute' => 'id_responsable',
                        'value' => $model->responsable ? $model->responsable->persona->getNombreCompleto(Persona::FORMATO_NOMBRE_A_OA_N_ON) : 'No definido',
                    ],
                    //'id_piso',
                    [   //muestra el nombre del piso seleccionado en el listado
                        'label' => 'Piso',
                        'attribute' => 'id_piso',
                        'value' => $model->piso ? $model->piso->descripcion : 'No definido',
                    ],
                    //'id_servicio',
                    [   //muestra el nombre del servivio seleccionado en el listado
                        'label' => 'Servicio',
                        'attribute' => 'id_servicio',
                        'value' => $model->servicio ? $model->servicio->nombre : 'No definido',
                    ],
                    'tipo_sala',
                ],
            ]) ?>
        </div>
    </div>
    <p>
        <?= Html::a('Volver', ['index'], ['class' => 'btn btn-success rounded-pill']) ?>
    </p>
</div>