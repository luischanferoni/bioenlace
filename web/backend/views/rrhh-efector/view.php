<?php

use yii\helpers\Html;
use yii\widgets\DetailView;

/* @var $this yii\web\View */
/* @var $model common\models\RrhhEfector */

$this->title = $model->id_rr_hh;
$this->params['breadcrumbs'][] = ['label' => 'Rrhh Efectors', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
\yii\web\YiiAsset::register($this);
?>
<div class="rrhh-efector-view">

    <h1><?= Html::encode($this->title) ?></h1>

    <p>
        <?= Html::a('Update', ['update', 'id_rr_hh' => $model->id_rr_hh, 'id_efector' => $model->id_efector], ['class' => 'btn btn-primary']) ?>
        <?= Html::a('Delete', ['delete', 'id_rr_hh' => $model->id_rr_hh, 'id_efector' => $model->id_efector], [
            'class' => 'btn btn-danger',
            'data' => [
                'confirm' => 'Are you sure you want to delete this item?',
                'method' => 'post',
            ],
        ]) ?>
    </p>

    <?= DetailView::widget([
        'model' => $model,
        'attributes' => [
            'id_rr_hh',
            'id_persona',
            'id_efector',
            'created_at',
            'updated_at',
            'deleted_at',
            'created_by',
            'updated_by',
            'deleted_by',
        ],
    ]) ?>

</div>
