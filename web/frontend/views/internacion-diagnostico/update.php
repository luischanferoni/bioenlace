<?php

use yii\helpers\Html;

/* @var $this yii\web\View */
/* @var $model common\models\SegNivelInternacionDiagnostico */

$this->title = 'Actualizar Seg Nivel Internacion Diagnostico: ' . $model->id;
$this->params['breadcrumbs'][] = ['label' => 'Seg Nivel Internacion Diagnosticos', 'url' => ['index']];
$this->params['breadcrumbs'][] = ['label' => $model->id, 'url' => ['view', 'id' => $model->id, 'id_internacion' => $model->id_internacion]];
$this->params['breadcrumbs'][] = 'Actualizar';
?>
<div class="seg-nivel-internacion-diagnostico-update">

    <h1><?= Html::encode($this->title) ?></h1>

    <?= $this->render('_form', [
        'model' => $model,
    ]) ?>

</div>
