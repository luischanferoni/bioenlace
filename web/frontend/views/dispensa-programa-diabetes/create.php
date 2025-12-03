<?php

use yii\helpers\Html;

/* @var $this yii\web\View */
/* @var $model common\models\DispensaProgramaDiabetes */

$this->title = 'Create Dispensa Programa Diabetes';
$this->params['breadcrumbs'][] = ['label' => 'Dispensa Programa Diabetes', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="dispensa-programa-diabetes-create">

    <h1><?= Html::encode($this->title) ?></h1>

    <?= $this->render('_form', [
        'model' => $model,
    ]) ?>

</div>
