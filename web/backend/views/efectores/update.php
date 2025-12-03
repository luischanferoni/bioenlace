<?php

use yii\helpers\Html;

/* @var $this yii\web\View */
/* @var $model common\models\Efector */

$this->title = 'Modificar Efector: ' . ' ' . $model->nombre;
$this->params['breadcrumbs'][] = ['label' => 'Efectors', 'url' => ['index']];
$this->params['breadcrumbs'][] = ['label' => $model->nombre, 'url' => ['view', 'id' => $model->id_efector]];
$this->params['breadcrumbs'][] = 'Modificar';
?>
<div class="efector-update">

    <h1><?= Html::encode($this->title) ?></h1>

    <?= $this->render('_form', [
        'model' => $model,
    ]) ?>

</div>
