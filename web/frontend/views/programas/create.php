<?php

use yii\helpers\Html;


/* @var $this yii\web\View */
/* @var $model common\models\Programa */

$this->title = 'Alta de Programas de Salud';
$this->params['breadcrumbs'][] = ['label' => 'Programas', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="programa-create">

    <h1><?= Html::encode($this->title) ?></h1>

    <?= $this->render('_form', [
        'model' => $model,
    ]) ?>

</div>
