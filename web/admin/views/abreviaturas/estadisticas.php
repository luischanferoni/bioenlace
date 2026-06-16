<?php
use yii\helpers\Html;

/* @var $this yii\web\View */
/* @var $estadisticas array */

$this->title = 'Estadísticas de Abreviaturas';
?>

<div class="abreviaturas-estadisticas">
    <h1><?= Html::encode($this->title) ?></h1>
    <pre><?= Html::encode(print_r($estadisticas, true)) ?></pre>
</div>


