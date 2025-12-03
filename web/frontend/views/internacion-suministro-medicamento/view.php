<?php

use yii\helpers\Html;
use yii\widgets\DetailView;

/* @var $this yii\web\View */
/* @var $model common\models\InfraestructuraSala */

$this->title = 'Suministro de medicamento ' . $model->id;
$this->params['breadcrumbs'][] = ['label' => 'Suministro de medicamento', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
\yii\web\YiiAsset::register($this);
?>
<div class="internacion-suministro-medicamento-view">


    <div class="card">
        <div class="card-header">
            <h4>Suministro de Medicamentos</h4>
        </div>
        <div class="card-body">
            <?= DetailView::widget([
                'model' => $model,
                'attributes' => [
                    //'id',
                    'fecha',
                    'hora',
                    [   //muestra el nombre del responsable seleccionado en el listado
                        'label' => 'Medicamento',
                        'attribute' => 'id_internacion_medicamento',
                        'value' => $model->internacionMedicamento->medicamentoSnomed->term,
                    ],
                    [   //muestra el nombre del responsable seleccionado en el listado
                        'label' => 'Quien Suministró',
                        'attribute' => 'id_rrhh',
                        'value' => $model->rrhhSuministra->rrhh->idPersona->nombreCompleto,
                    ],
                    'observacion',
                ],
            ]) ?>
        </div>
    </div>

    <p>
        <?= Html::a('Actualizar', ['update', 'id' => $model->id], ['class' => 'btn btn-primary']) ?>
        <?= Html::a('Eliminar', ['delete', 'id' => $model->id], [
            'class' => 'btn btn-danger',
            'data' => [
                'confirm' => '¿Está seguro de eliminar este elemento?',
                'method' => 'post',
            ],
        ]) ?>
    </p>



    <p>
        <?= Html::a('Volver', ['index'], ['class' => 'btn btn-success']) ?>
    </p>
</div>