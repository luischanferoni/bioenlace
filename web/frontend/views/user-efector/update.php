<?php

use yii\helpers\Html;

/* @var $this yii\web\View */
/* @var $model common\models\UserEfector */

$this->title = 'Modificar Aignacion de Efector: ' . ' ' . $model->id_user;
$this->params['breadcrumbs'][] = ['label' => 'User Efectors', 'url' => ['index']];
$this->params['breadcrumbs'][] = ['label' => $model->id_user, 'url' => ['view', 'id_user' => $model->id_user, 'id_efector' => $model->id_efector]];
$this->params['breadcrumbs'][] = 'Modificar';
?>
<div class="user-efector-update">

    <h1><?= Html::encode($this->title) ?></h1>

    <?= $this->render('_form', [
        'model' => $model,
    ]) ?>

</div>
