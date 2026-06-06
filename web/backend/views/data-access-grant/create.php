<?php

use yii\helpers\Html;

/* @var $this yii\web\View */
/* @var $model common\models\DataAccess\DataAccessRoleGrant */

$this->title = 'Nuevo grant DataAccess';
$this->params['breadcrumbs'][] = ['label' => 'Permisos por atributo', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="data-access-grant-create">

    <h1><?= Html::encode($this->title) ?></h1>

    <?= $this->render('_form', ['model' => $model]) ?>

</div>
