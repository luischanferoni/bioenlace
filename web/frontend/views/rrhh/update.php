<?php

use yii\helpers\Html;

/* @var $this yii\web\View */
/* @var $model common\models\Rrhh */

$this->title = 'Actualizar Rrhh: ' . ' ' . $model->id_rr_hh;
$this->params['breadcrumbs'][] = ['label' => 'Rrhhs', 'url' => ['index']];
$this->params['breadcrumbs'][] = ['label' => $model->id_rr_hh, 'url' => ['view', 'id' => $model->id_rr_hh]];
$this->params['breadcrumbs'][] = 'Actualizar';
?>
<div class="rrhh-update">

    <h1><?= Html::encode($this->title) ?></h1>

    <?= $this->render('_form', [
        'model' => $model,
        'model_persona' => $model_persona,
        /*'model_efector' => $model_efector,
        'model_condiciones_laborales' => $model_condiciones_laborales ,*/
        'model_rr_hh_efector' => $model_rr_hh_efector,
        //'model_servicios' => $model_servicios,
    ]) ?>

</div>
