<?php

use yii\helpers\Html;
use yii\widgets\DetailView;

/* @var $this yii\web\View */
/* @var $model common\models\ServiciosEfector */

$this->title = 'SERVICIO - ' .$model->id_servicio;
$this->params['breadcrumbs'][] = ['label' => 'Servicios Efectors', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="servicios-efector-view">


    <div class="card">

        <div class="card-header  bg-soft-info">
            <h1><?= Html::encode($this->title) ?></h1>
        </div>

        <div class="card-body">

                <?= DetailView::widget([
                'model' => $model,
                'attributes' => [
                    'servicio.nombre',
                    'efector.nombre',
                    'horario'
                ],
            ]) ?>

                <span class="float-end">
                    <?= Html::a('Actualizar', ['update', 'id_servicio' => $model->id_servicio, 'id_efector' => $model->id_efector,'horario' => $model->horario], ['class' => 'btn btn-primary']) ?>
                    <?= Html::a('Delete', ['delete', 'id_servicio' => $model->id_servicio, 'id_efector' => $model->id_efector], [
                        'class' => 'btn btn-danger',
                        'data' => [
                            'confirm' => 'Realmente desea borrar este registro?',
                            'method' => 'post',
                        ],
                    ]) ?>
                </span>


        </div>

    </div>
 

</div>
