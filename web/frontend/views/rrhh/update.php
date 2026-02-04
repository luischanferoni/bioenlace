<?php

use yii\helpers\Html;

/* @var $this yii\web\View */
/* @var $model common\models\RrhhEfector */
/* @var $model_persona common\models\Persona|null */

$this->title = 'Actualizar RRHH: ' . $model->id_rr_hh;
$this->params['breadcrumbs'][] = ['label' => 'RRHH', 'url' => ['index']];
$this->params['breadcrumbs'][] = ['label' => $model->id_rr_hh, 'url' => ['view', 'id' => $model->id_rr_hh]];
$this->params['breadcrumbs'][] = 'Actualizar';
?>
<div class="rrhh-update">

    <h1><?= Html::encode($this->title) ?></h1>

    <?= $this->render('_form', [
        'model' => $model,
        'model_persona' => $model_persona,
    ]) ?>

</div>
