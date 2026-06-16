<?php

use yii\helpers\Html;

/* @var $this yii\web\View */
/* @var $model common\models\Barrios */

$this->title = 'Editar Barrio: ' . $model->nombre;
$this->params['breadcrumbs'][] = ['label' => 'Barrios', 'url' => ['index']];
$this->params['breadcrumbs'][] = ['label' => $model->id_barrio, 'url' => ['view', 'id' => $model->id_barrio]];
$this->params['breadcrumbs'][] = 'Actualizar';
?>
<div class="barrios-update">

    <h1><?= Html::encode($this->title) ?></h1>

    <?= $this->render('_form', [
        'model' => $model,
        'provincia' => $provincia,
        'departamento' => $departamento,
        'localidad' => $localidad,
    ]) ?>

</div>
