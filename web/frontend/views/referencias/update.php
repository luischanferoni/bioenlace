<?php

use yii\helpers\Html;

/* @var $this yii\web\View */
/* @var $model common\models\referencia */

$this->title = 'Actualizar Referencia: ' . ' ' . $model->id_referencia;
$this->params['breadcrumbs'][] = ['label' => 'Referencias', 'url' => ['index']];
$this->params['breadcrumbs'][] = ['label' => $model->id_referencia, 'url' => ['view', 'id' => $model->id_referencia]];
$this->params['breadcrumbs'][] = 'Actualizar';
?>
<div class="referencia-update">

    <h1><?= Html::encode($this->title) ?></h1>

    <?= $this->render('_form', [
        'model' => $model,
        'persona' => $persona,
    ]) ?>

</div>
