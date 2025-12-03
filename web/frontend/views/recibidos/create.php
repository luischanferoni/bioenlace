<?php

use yii\helpers\Html;


/* @var $this yii\web\View */
/* @var $model common\models\Recibidos */

$this->title = 'Nuevo Recibido';
$this->params['breadcrumbs'][] = ['label' => 'Recibidos', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="recibidos-create">

    <h1><?= Html::encode($this->title) ?></h1>

    <?= $this->render('_form', [
        'model' => $model,
    ]) ?>

</div>
