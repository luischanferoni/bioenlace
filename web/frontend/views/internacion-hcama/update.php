<?php

use yii\helpers\Html;

/* @var $this yii\web\View */
/* @var $model common\models\SegNivelInternacionHcama */

$this->title = 'Update Seg Nivel Internacion Hcama: ' . $model->id;
$this->params['breadcrumbs'][] = ['label' => 'Seg Nivel Internacion Hcamas', 'url' => ['index']];
$this->params['breadcrumbs'][] = ['label' => $model->id, 'url' => ['view', 'id' => $model->id]];
$this->params['breadcrumbs'][] = 'Update';
?>
<div class="seg-nivel-internacion-hcama-update">

    <h1><?= Html::encode($this->title) ?></h1>

    <?= $this->render('_form', [
        'model' => $model,
    ]) ?>

</div>
