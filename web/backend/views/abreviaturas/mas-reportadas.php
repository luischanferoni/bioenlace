<?php
use yii\helpers\Html;

/* @var $this yii\web\View */
/* @var $abreviaturas array */
/* @var $limite int */

$this->title = 'Abreviaturas más reportadas';
?>

<div class="abreviaturas-mas-reportadas">
    <h1><?= Html::encode($this->title) ?></h1>
    <p>Límite: <?= $limite ?></p>
    <ul>
        <?php foreach ($abreviaturas as $a): ?>
            <li><?= Html::encode($a->abreviatura ?? $a) ?></li>
        <?php endforeach; ?>
    </ul>
</div>


