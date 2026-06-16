<?php

use yii\helpers\Html;

/* @var $this yii\web\View */
/* @var $model common\models\Novedad */

$this->title = 'Create Novedad';
$this->params['breadcrumbs'][] = ['label' => 'Novedads', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="novedad-create">

    <h1><?= Html::encode($this->title) ?></h1>

    <?= $this->render('_form', [
        'model' => $model,
    ]) ?>

</div>
