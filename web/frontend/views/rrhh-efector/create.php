<?php

use yii\helpers\Html;

/* @var $this yii\web\View */
/* @var $model common\models\RrhhEfector */

$this->title = 'Asignar RRHH a este Efector';
$this->params['breadcrumbs'][] = ['label' => 'Recursos humanos', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>

    <?= $this->render('_form', [
        'modeloRrhhEfector' => $modeloRrhhEfector,
        'modelosRrhhServicios' => $modelosRrhhServicios,
        'modelosRrhhCondicionesLaborales' => $modelosRrhhCondicionesLaborales,
        'modelosAgendas' => $modelosAgendas,
        'conServiciosParaSalud' => $conServiciosParaSalud
    ]) ?>
