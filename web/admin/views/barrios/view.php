<?php

use yii\helpers\Html;
use yii\widgets\DetailView;

/* @var $this yii\web\View */
/* @var $model common\models\Barrios */

$this->title = $model->id_barrio . ' - ' . $model->nombre;
$this->params['breadcrumbs'][] = ['label' => 'Barrios', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="barrios-view">

    <h1><?= Html::encode($this->title) ?></h1>

    <p>
        <?= Html::a('Actualizar', ['update', 'id' => $model->id_barrio], ['class' => 'btn btn-primary']) ?>
        <?= Html::a('Delete', ['delete', 'id' => $model->id_barrio], [
            'class' => 'btn btn-danger',
            'data' => [
                'confirm' => 'Realmente desea borrar este registro?',
                'method' => 'post',
            ],
        ]) ?>
    </p>

    <?= DetailView::widget([
        'model' => $model,
        'attributes' => [
            'id_barrio',
            'nombre',
            'rural_urbano',
            'id_localidad',
        ],
    ]) ?>

    <p>
        <?= Html::a('Volver', ['index'], ['class' => 'btn btn-success']) ?>
    </p>

</div>
