<?php

use yii\helpers\Html;

/* @var $this yii\web\View */
/* @var $model common\models\DataAccess\DataAccessRoleGrant */

$this->title = 'Editar grant #' . $model->id;
$this->params['breadcrumbs'][] = ['label' => 'Permisos por atributo', 'url' => ['index']];
$this->params['breadcrumbs'][] = ['label' => 'Grant #' . $model->id, 'url' => ['view', 'id' => $model->id]];
$this->params['breadcrumbs'][] = 'Editar';
?>
<div class="data-access-grant-update">

    <h1><?= Html::encode($this->title) ?></h1>

    <?= $this->render('_form', ['model' => $model]) ?>

</div>
