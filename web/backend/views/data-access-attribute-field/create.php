<?php

use yii\helpers\Html;

/* @var $this yii\web\View */
/* @var $model common\models\DataAccess\DataAccessAttributeField */

$this->title = 'Nuevo campo';
$this->params['breadcrumbs'][] = ['label' => 'Campos por grupo', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="data-access-attribute-field-create">

    <h1 class="h2"><?= Html::encode($this->title) ?></h1>

    <?= $this->render('_form', ['model' => $model]) ?>

</div>
