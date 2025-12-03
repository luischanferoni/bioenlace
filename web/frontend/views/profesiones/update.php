<?php

use yii\helpers\Html;

/* @var $this yii\web\View */
/* @var $model common\models\Profesiones */

$this->title = 'Actualizar Profesiones: ' . ' ' . $model->id_profesion;
$this->params['breadcrumbs'][] = ['label' => 'Profesiones', 'url' => ['index']];
$this->params['breadcrumbs'][] = ['label' => $model->id_profesion, 'url' => ['view', 'id' => $model->id_profesion]];
$this->params['breadcrumbs'][] = 'Actualizar';
?>

<div class="profesiones-update">

    <h1><?= Html::encode($this->title) ?></h1>

    <?= $this->render('_form', [
        'model' => $model,
    ]) ?>
    
   

</div>
   