<?php

use yii\helpers\Html;


/* @var $this yii\web\View */
/* @var $model common\models\Enviados */

$this->title = 'Nuevo Enviado';
$this->params['breadcrumbs'][] = ['label' => 'Enviados', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="enviados-create">

    <h1><?= Html::encode($this->title) ?></h1>

    <?= $this->render('_form', [
        'model' => $model,
    ]) ?>

</div>
