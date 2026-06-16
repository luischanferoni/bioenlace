<?php

use yii\helpers\Html;


/* @var $this yii\web\View */
/* @var $model common\models\Efector */

$this->title = 'Nuevo Efector';
$this->params['breadcrumbs'][] = ['label' => 'Efectors', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="efector-create">

    <h1><?= Html::encode($this->title) ?></h1>

    <?= $this->render('_form', [
        'model' => $model,
    ]) ?>

</div>
