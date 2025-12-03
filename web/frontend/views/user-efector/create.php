<?php

use yii\helpers\Html;


/* @var $this yii\web\View */
/* @var $model common\models\UserEfector */

$this->title = 'Asignacion de Efector';
$this->params['breadcrumbs'][] = ['label' => 'Usuarios Efectores', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="user-efector-create">

    <h1><?= Html::encode($this->title) ?></h1>

    <?= $this->render('_form', [
        'model' => $model,
    ]) ?>

</div>
