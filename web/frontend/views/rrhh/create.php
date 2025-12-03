<?php

use yii\helpers\Html;


/* @var $this yii\web\View */
/* @var $model common\models\Rrhh */

$this->title = 'Crear RRHH para: '.$model_persona->apellido.', '.$model_persona->nombre;
$this->params['breadcrumbs'][] = ['label' => 'Rrhhs', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;

?>
<div class="rrhh-create">

    <h1><?= Html::encode($this->title) ?></h1>

    <?= $this->render('_form', [
        'model' => $model,
        'model_persona' => $model_persona,
        //'model_efector' => $model_efector,
        //'model_condiciones_laborales' => $model_condiciones_laborales ,
         'model_rr_hh_efector' => $model_rr_hh_efector,
         //'model_servicios' => $model_servicios,
    ]) ?>

</div>
