<?php

use yii\helpers\Html;
use yii\widgets\DetailView;

/* @var $this yii\web\View */
/* @var $model common\models\SegNivelInternacionAtencionesEnfermeria */

$this->title = $model->id;
$this->params['breadcrumbs'][] = ['label' => 'Seg Nivel Internacion Atenciones Enfermerias', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
\yii\web\YiiAsset::register($this);
?>
<div class="seg-nivel-internacion-atenciones-enfermeria-view">

    <h1><?= Html::encode($this->title) ?></h1>

    <p>
        <?= Html::a('Actualizar', ['update', 'id' => $model->id], ['class' => 'btn btn-primary']) ?>
        <?= Html::a('Delete', ['delete', 'id' => $model->id], [
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
            'id',
            'datos',
            'observaciones:ntext',
            'id_internacion',
            'id_user',
            'fecha_creacion',
        ],
    ]) ?>

</div>
