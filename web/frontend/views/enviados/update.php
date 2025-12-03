<?php

use yii\helpers\Html;

/* @var $this yii\web\View */
/* @var $model common\models\Enviados */

$this->title = 'Actualizar Enviados: ' . ' ' . $model->id;
$this->params['breadcrumbs'][] = ['label' => 'Enviados', 'url' => ['index']];
$this->params['breadcrumbs'][] = ['label' => $model->id, 'url' => ['view', 'id' => $model->id]];
$this->params['breadcrumbs'][] = 'Actualizar';
?>
<div class="enviados-update">

    <h1><?= Html::encode($this->title) ?></h1>

    <?= $this->render('_form', [
        'model' => $model,
    ]) ?>

</div>
