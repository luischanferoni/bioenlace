<?php

use yii\helpers\Html;

/* @var $this yii\web\View */
/* @var $model common\models\DataAccess\DataAccessRoleGrant */

$this->title = 'Grant #' . $model->id;
$this->params['breadcrumbs'][] = ['label' => 'Permisos por atributo', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="data-access-grant-view">

    <h1><?= Html::encode($this->title) ?></h1>

    <p>
        <?= Html::a('Editar', ['update', 'id' => $model->id], ['class' => 'btn btn-primary']) ?>
        <?= Html::a('Eliminar', ['delete', 'id' => $model->id], [
            'class' => 'btn btn-danger',
            'data' => [
                'confirm' => '¿Eliminar este grant?',
                'method' => 'post',
            ],
        ]) ?>
        <?= Html::a('Volver al listado', ['index'], ['class' => 'btn btn-outline-secondary']) ?>
    </p>

    <?= $this->render('_detail', ['model' => $model]) ?>

</div>
