<?php

use yii\helpers\Html;

/* @var $this yii\web\View */

$this->title = 'Búsqueda de Persona';
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="buscar-persona">
    <div class="alert alert-info">
        El flujo MPI de búsqueda fue reemplazado por el alta con lector DNI o Didit.
    </div>
    <p>
        <?= Html::a('Ir a registrar paciente', ['registrar-paciente'], ['class' => 'btn btn-primary']) ?>
    </p>
</div>
