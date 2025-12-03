<?php

use yii\helpers\Html;

/* @var $this yii\web\View */
/* @var $model common\models\SegNivelInternacionAtencionesEnfermeria */

$this->title = 'Nueva Atención de Enfermería';
$this->params['breadcrumbs'][] = ['label' => 'Atenciones de Enfermería', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="seg-nivel-internacion-atenciones-enfermeria-create">

    <h2><?= Html::encode($this->title) ?></h2>
    <?= $errores; ?>
    <?= $this->render('_form', [
        'model' => $model,
        'id_internacion' => $id_internacion,
    ]) ?>

</div>
