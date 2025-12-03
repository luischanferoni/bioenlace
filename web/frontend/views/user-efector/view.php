<?php

use yii\helpers\Html;
use yii\widgets\DetailView;

/* @var $this yii\web\View */
/* @var $model common\models\UserEfector */

$this->title = $model->id_user;
$this->params['breadcrumbs'][] = ['label' => 'Asignar Efectores', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="user-efector-view">

    <h1><?= Html::encode($this->title) ?></h1>

    <p>
        <?= Html::a('Modificar', ['update', 'id_user' => $model->id_user, 'id_efector' => $model->id_efector], ['class' => 'btn btn-primary']) ?>
        <?= Html::a('Borrar', ['delete', 'id_user' => $model->id_user, 'id_efector' => $model->id_efector], [
            'class' => 'btn btn-danger',
            'data' => [
                'confirm' => 'Esta seguro que desea borrar este registro?',
                'method' => 'post',
            ],
        ]) ?>
    </p>

    <?= DetailView::widget([
        'model' => $model,
        'attributes' => [
            
            [
            'label' => 'Usuario',
            'value' => $model->user->username
            ],
                    [
            'label' => 'Efector',
            'value' =>  $model->efector->nombre
            
            ],
        ],
    ]) ?>

</div>
