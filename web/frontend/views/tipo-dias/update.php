<?php

use yii\helpers\Html;

/* @var $this yii\web\View */
/* @var $model common\models\Tipo_dia */

$this->title = 'Actualizar Tipo Dia: ' . ' ' . $model->id_tipo_dia;
$this->params['breadcrumbs'][] = ['label' => 'Tipo Dias', 'url' => ['index']];
$this->params['breadcrumbs'][] = ['label' => $model->id_tipo_dia, 'url' => ['view', 'id' => $model->id_tipo_dia]];
$this->params['breadcrumbs'][] = 'Actualizar';
?>
<div class="tipo-dia-update">

    <h1><?= Html::encode($this->title) ?></h1>

    <?= $this->render('_form', [
        'model' => $model,
    ]) ?>

</div>
