<?php

use yii\helpers\Html;


/* @var $this yii\web\View */
/* @var $model common\models\Agenda_rrhh */

$this->title = 'Nueva Agenda';
$this->params['breadcrumbs'][] = ['label' => 'Agenda Rrhhs', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="agenda-rrhh-create">

    <h1><?= Html::encode($this->title) ?></h1>

    <?= $this->render('_form', [
        'model' => $model,
    ]) ?>

</div>
