<?php

use yii\helpers\Html;
use yii\widgets\DetailView;

/* @var $this yii\web\View */
/* @var $model common\models\SensibilidadCategoria */

$this->title = $model->nombre;
$this->params['breadcrumbs'][] = ['label' => 'Categorías de sensibilidad', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="sensibilidad-categoria-view">

    <h1><?= Html::encode($this->title) ?></h1>

    <p>
        <?= Html::a('Actualizar', ['update', 'id' => $model->id], ['class' => 'btn btn-primary']) ?>
        <?= Html::a('Eliminar', ['delete', 'id' => $model->id], [
            'class' => 'btn btn-danger',
            'data' => [
                'confirm' => '¿Eliminar esta categoría? Se eliminarán también los mapeos y reglas asociados.',
                'method' => 'post',
            ],
        ]) ?>
        <?= Html::a('Mapeos SNOMED', ['sensibilidad-mapeo/index', 'SensibilidadMapeoSnomedBusqueda[id_categoria]' => $model->id], ['class' => 'btn btn-outline-secondary']) ?>
        <?php
        $regla = $model->regla;
        if ($regla) {
            echo Html::a('Regla (generalizar/ocultar por servicio)', ['sensibilidad-regla/update', 'id' => $regla->id], ['class' => 'btn btn-outline-secondary']);
        } else {
            echo Html::a('Crear regla', ['sensibilidad-regla/create', 'id_categoria' => $model->id], ['class' => 'btn btn-outline-secondary']);
        }
        ?>
    </p>

    <?= DetailView::widget([
        'model' => $model,
        'attributes' => [
            'id',
            'nombre',
            'descripcion:ntext',
        ],
    ]) ?>

</div>
