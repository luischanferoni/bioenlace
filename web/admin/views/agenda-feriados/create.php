<?php

use yii\helpers\Html;

/* @var $this yii\web\View */
/* @var $model common\models\AgendaFeriados */

$this->title = 'Create Agenda Feriados';
$this->params['breadcrumbs'][] = ['label' => 'Agenda Feriados', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="agenda-feriados-create">

    <?= $this->render('_form', [
        'model' => $model,
    ]) ?>

</div>
