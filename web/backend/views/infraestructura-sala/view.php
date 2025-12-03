<?php

use yii\helpers\Html;
use yii\widgets\DetailView;

/* @var $this yii\web\View */
/* @var $model common\models\InfraestructuraSala */

$this->title = 'Sala N° '.$model->nro_sala;
$this->params['breadcrumbs'][] = ['label' => 'Infraestructura Salas', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
\yii\web\YiiAsset::register($this);
?>
<div class="infraestructura-sala-view">

    <h1><?= Html::encode($this->title) ?></h1>

    <p>
        <?= Html::a('Actualizar', ['update', 'id' => $model->id], ['class' => 'btn btn-primary']) ?>
        <?= Html::a('Eliminar', ['delete', 'id' => $model->id], [
            'class' => 'btn btn-danger',
            'data' => [
                'confirm' => '¿Está seguro de elimir este elemento?',
                'method' => 'post',
            ],
        ]) ?>
    </p>

    <?= DetailView::widget([
        'model' => $model,
        'attributes' => [
            //'id',
            'nro_sala',
            'descripcion',
            'covid',
            //'id_responsable',
            [   //muestra el nombre del responsable seleccionado en el listado
                'label'=> 'Responsable',
                'attribute'=>'id_responsable',
                'value'=> $model->responsable?$model->responsable->rrhh->idPersona->nombreCompleto: 'No definido',
            ],
            //'id_piso',
            [   //muestra el nombre del piso seleccionado en el listado
                'label'=> 'Piso',
                'attribute'=>'id_piso',
                'value'=>$model->piso? $model->piso->descripcion: 'No definido',
            ],
            //'id_servicio',
            [   //muestra el nombre del servivio seleccionado en el listado
                'label'=> 'Servicio',
                'attribute'=>'id_servicio',
                'value'=>$model->servicio? $model->servicio->nombre: 'No definido',
            ],
            'tipo_sala',
        ],
    ]) ?>

    <p>
        <?= Html::a('Volver', ['index'], ['class' => 'btn btn-success']) ?>
    </p>
</div>
