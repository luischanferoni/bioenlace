<?php

use yii\helpers\Html;


/* @var $this yii\web\View */
/* @var $model common\models\Recibidos */

$this->title = 'Nueva Receta';
$this->params['breadcrumbs'][] = ['label' => 'Receta', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>

<?= $this->render('_form') ?>
