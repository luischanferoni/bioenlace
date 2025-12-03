<?php

use yii\helpers\Html;
use yii\helpers\Url;
use yii\helpers\Json;

/* @var $this yii\web\View */
/* @var $configuracionPasos array */
/* @var $modelConsulta common\models\Consulta */
/* @var $paciente common\models\Persona */
?>

<div class="consulta-unificada-index">
    <div class="container-fluid">
        <!-- Formulario unificado -->
        <?= $this->render('_form', [
            'configuracionPasos' => $configuracionPasos,
            'modelConsulta' => $modelConsulta,
            'paciente' => $paciente,
        ]) ?>
    </div>
</div>
