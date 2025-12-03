<?php

use yii\helpers\Html;

/* @var $this yii\web\View */
/* @var $model common\models\Programa */

$this->title = 'Actualizar Programa: ' . ' ' . $model->id_programa;
$this->params['breadcrumbs'][] = ['label' => 'Programas', 'url' => ['index']];
$this->params['breadcrumbs'][] = ['label' => $model->id_programa, 'url' => ['view', 'id' => $model->id_programa]];
$this->params['breadcrumbs'][] = 'Actualizar';
?>
<div class="programa-update">

    <h1><?= Html::encode($this->title) ?></h1>

    <?= $this->render('_form', [
        'model' => $model,
    ]) ?>

</div>
