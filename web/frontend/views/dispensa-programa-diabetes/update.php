<?php

use yii\helpers\Html;

/* @var $this yii\web\View */
/* @var $model common\models\DispensaProgramaDiabetes */

$this->title = 'Update Dispensa Programa Diabetes: ' . $model->id;
$this->params['breadcrumbs'][] = ['label' => 'Dispensa Programa Diabetes', 'url' => ['index']];
$this->params['breadcrumbs'][] = ['label' => $model->id, 'url' => ['view', 'id' => $model->id]];
$this->params['breadcrumbs'][] = 'Update';
?>
<div class="dispensa-programa-diabetes-update">

    <h1><?= Html::encode($this->title) ?></h1>

    <?= $this->render('_form', [
        'model' => $model,
    ]) ?>

</div>
