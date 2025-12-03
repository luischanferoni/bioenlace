<?php

use yii\helpers\Html;

/* @var $this yii\web\View */
/* @var $model common\models\SegNivelInternacion */

$this->title = 'Alta Internacion: ' . $model->id;
$this->params['breadcrumbs'][] = ['label' => 'Seg Nivel Internacions', 'url' => ['index']];
$this->params['breadcrumbs'][] = ['label' => $model->id, 'url' => ['view', 'id' => $model->id]];
$this->params['breadcrumbs'][] = 'Actualizar';
?>
<div class="seg-nivel-internacion-update">
    <div class="card">
        <div class="card-header bg-soft-info">
            <h4><?= Html::encode($this->title) ?></h4>
        </div>

        <div class="card-body">
            <?= $this->render('_formupdate', [
                'model' => $model,
                'modal_id' => $modal_id,
            ]) ?>

        </div>
    </div>

</div>