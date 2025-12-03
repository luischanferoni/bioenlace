<?php

use yii\helpers\Html;
use yii\widgets\DetailView;

/* @var $this yii\web\View */
/* @var $model common\models\Profesiones */

$this->title = $model->id_profesion;
$this->params['breadcrumbs'][] = ['label' => 'Profesiones', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="profesiones-view">

    <h1><?= Html::encode($this->title) ?></h1>

    <p>
        <?= Html::a('Actualizar', ['update', 'id' => $model->id_profesion], ['class' => 'btn btn-primary']) ?>
        <?= Html::a('Delete', ['delete', 'id' => $model->id_profesion], [
            'class' => 'btn btn-danger',
            'data' => [
                'confirm' => 'Realmente desea borrar este registro?',
                'method' => 'post',
            ],
        ])?>
    </p>

    <?= DetailView::widget([
        'model' => $model,
        'attributes' => [
            'id_profesion',
            'nombre',
        ],
    ]) ?>
     <div class="especialidades-form">
        
        <?= Html::a('Agregar Especialidad', ['..\especialidades'], ['class' => 'btn btn-success']) ?>
    </div> 

</div>
