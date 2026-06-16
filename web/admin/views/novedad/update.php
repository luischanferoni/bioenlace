<?php

use yii\helpers\Html;

/* @var $this yii\web\View */
/* @var $model common\models\Novedad */

$this->title = 'Update Novedad: ' . $model->id;
$this->params['breadcrumbs'][] = ['label' => 'Novedads', 'url' => ['index']];
$this->params['breadcrumbs'][] = ['label' => $model->id, 'url' => ['view', 'id' => $model->id]];
$this->params['breadcrumbs'][] = 'Update';
?>
<div class="novedad-update">

    <h1><?= Html::encode($this->title) ?></h1>

    <?= $this->render('_form', [
        'model' => $model,
    ]) ?>

</div>
