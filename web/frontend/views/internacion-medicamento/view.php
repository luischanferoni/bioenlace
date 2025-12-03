<?php

use yii\helpers\Html;
use yii\widgets\DetailView;

/* @var $this yii\web\View */
/* @var $model common\models\SegNivelInternacionMedicamento */

$this->title = $model->id;
$this->params['breadcrumbs'][] = ['label' => 'Medicamentos', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
\yii\web\YiiAsset::register($this);
?>
<div class="seg-nivel-internacion-medicamento-view">

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
            //'conceptId',
            [   //muestra el nombre de la sala seleccionado en el listado
                'label'=> 'Concepto',
                'attribute'=>'conceptId',
                'value'=>$model->medicamentoSnomed->term,
            ],
            'cantidad',
            'dosis_diaria',
            //'id_internacion',
        ],
    ]) ?>

    <p>
        <?= Html::a('Volver', ['internacion/view', 'id'=> $model->id_internacion], ['class' => 'btn btn-success']) ?>
    </p>


</div>
