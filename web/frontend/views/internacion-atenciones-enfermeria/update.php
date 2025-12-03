<?php

use yii\helpers\Html;

/* @var $this yii\web\View */
/* @var $model common\models\SegNivelInternacionAtencionesEnfermeria */

$this->title = 'Actualizar Seg Nivel Internacion Atenciones Enfermeria: ' . $model->id;
$this->params['breadcrumbs'][] = ['label' => 'Seg Nivel Internacion Atenciones Enfermerias', 'url' => ['index']];
$this->params['breadcrumbs'][] = ['label' => $model->id, 'url' => ['view', 'id' => $model->id]];
$this->params['breadcrumbs'][] = 'Actualizar';
?>
<div class="seg-nivel-internacion-atenciones-enfermeria-update">

    <h1><?= Html::encode($this->title) ?></h1>

    <?= $this->render('_form', [
        'model' => $model,
    ]) ?>

</div>
