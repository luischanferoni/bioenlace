<?php

use yii\helpers\Html;

/* @var $this yii\web\View */
/* @var $model common\models\SegNivelInternacion */

$this->title = 'Nueva Internación';
$this->params['breadcrumbs'][] = ['label' => 'Internaciones', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="seg-nivel-internacion-create">

    <div class="card">
        <div class="card-header bg-soft-info">
            <h1><?= Html::encode($this->title) ?></h1>
        </div>
    </div>

    <?= $this->render('_form', [
        'model' => $model,
        'model_cama' => $model_cama,
        'persona' => $persona,
        'telefono'=> $telefono,
        'coberturas'=> $coberturas,
        'efectores' => $efectores
    ]) ?>

</div>