<?php

use yii\helpers\Html;

/* @var $this yii\web\View */
/* @var $model common\models\InfoContentArticle */

$this->title = 'Editar: ' . $model->title;
$this->params['breadcrumbs'][] = ['label' => 'Contenido informativo', 'url' => ['index']];
$this->params['breadcrumbs'][] = ['label' => $model->title, 'url' => ['view', 'id' => $model->id]];
$this->params['breadcrumbs'][] = 'Editar';
?>
<div class="info-content-article-update">

    <h1><?= Html::encode($this->title) ?></h1>

    <?= $this->render('_form', ['model' => $model]) ?>

</div>
