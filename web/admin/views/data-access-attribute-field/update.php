<?php

use yii\helpers\Html;

/* @var $this yii\web\View */
/* @var $model common\models\DataAccess\DataAccessAttributeField */

$this->title = 'Editar: ' . $model->field_name;
$this->params['breadcrumbs'][] = ['label' => 'Campos por grupo', 'url' => ['index', 'group' => $model->entity_group_key]];
$this->params['breadcrumbs'][] = ['label' => $model->field_name, 'url' => ['view', 'id' => $model->id]];
$this->params['breadcrumbs'][] = 'Editar';
?>
<div class="data-access-attribute-field-update">

    <h1 class="h2"><?= Html::encode($this->title) ?></h1>

    <?= $this->render('_form', ['model' => $model]) ?>

</div>
