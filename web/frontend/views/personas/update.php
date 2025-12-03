<?php

use yii\helpers\Html;

/* @var $this yii\web\View */
/* @var $model common\models\persona */

$this->title = 'Actualizacion de Datos Personales';
$this->params['breadcrumbs'][] = ['label' => 'Personas', 'url' => ['index']];
$this->params['breadcrumbs'][] = ['label' => $model->id_persona, 'url' => ['view', 'id' => $model->id_persona]];
$this->params['breadcrumbs'][] = 'Actualizar';
?>
<div class="persona-update">

    <div class="card">
        <div class="card-header bg-soft-info">
            <h1><?= Html::encode($this->title) . ': ' . $model->apellido . ', ' . $model->nombre ?></h1>
        </div>
    </div>

    <?= $this->render('_form', [
        'model' => $model,
        'model_persona_telefono' => $model_persona_telefono,
        'model_tipo_telefono' => $model_tipo_telefono,
        'model_domicilio' => $model_domicilio,
        'model_localidad' => $model_localidad,
        'domicilios' => $domicilios,
        'tels' => $tels,
        'mailsxpersona' => $mailsxpersona,
        'model_persona_hc' => $model_persona_hc
    ]) ?>

</div>