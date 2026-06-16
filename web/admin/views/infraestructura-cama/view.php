<?php

use yii\helpers\Html;
use yii\widgets\DetailView;

/* @var $this yii\web\View */
/* @var $model common\models\InfraestructuraCama */

$this->title ='Cama '. $model->id;
$this->params['breadcrumbs'][] = ['label' => 'Infraestructura Camas', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
\yii\web\YiiAsset::register($this);
?>
<div class="infraestructura-cama-view">

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
            'nro_cama',
            'respirador',
            'monitor',
            [   //muestra el nombre de la sala seleccionado en el listado
                'label'=> 'Sala',
                'attribute'=>'id_sala',
                'value'=>$model->sala->descripcion,
            ],
            'estado',
        ],
    ]) ?>

    <p>
        <?= Html::a('Volver', ['index'], ['class' => 'btn btn-success']) ?>
    </p>

</div>
