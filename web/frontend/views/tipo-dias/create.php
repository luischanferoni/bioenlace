<?php

use yii\helpers\Html;


/* @var $this yii\web\View */
/* @var $model common\models\Tipo_dia */

$this->title = 'Nuevo Tipo Dia';
$this->params['breadcrumbs'][] = ['label' => 'Tipo Dias', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="tipo-dia-create">

    <h1><?= Html::encode($this->title) ?></h1>

    <?= $this->render('_form', [
        'model' => $model,
    ]) ?>

</div>
