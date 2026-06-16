<?php

use yii\helpers\Html;

/* @var $this yii\web\View */
/* @var $model common\models\InfraestructuraCama */

$this->title = 'Crear Nueva Cama';
$this->params['breadcrumbs'][] = ['label' => 'Camas', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="infraestructura-cama-create">

    <h1><?= Html::encode($this->title) ?></h1>

    <?= $this->render('_form', [
        'model' => $model,
    ]) ?>

</div>
