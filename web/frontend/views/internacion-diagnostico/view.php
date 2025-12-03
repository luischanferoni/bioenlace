<?php

use yii\helpers\Html;
use yii\widgets\DetailView;

/* @var $this yii\web\View */
/* @var $model common\models\SegNivelInternacionDiagnostico */

$this->title = $model->id;
$this->params['breadcrumbs'][] = ['label' => 'Seg Nivel Internacion Diagnosticos', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
\yii\web\YiiAsset::register($this);
?>
<div class="seg-nivel-internacion-diagnostico-view">

    <h1><?= Html::encode($this->title) ?></h1>

    <p>
        <?= Html::a('Actualizar', ['update', 'id' => $model->id, 'id_internacion' => $model->id_internacion], ['class' => 'btn btn-primary']) ?>
        <?= Html::a('Delete', ['delete', 'id' => $model->id, 'id_internacion' => $model->id_internacion], [
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
            'id_internacion',
            'conceptId',
        ],
    ]) ?>

</div>
