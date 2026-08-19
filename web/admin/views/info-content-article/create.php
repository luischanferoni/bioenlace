<?php

use yii\helpers\Html;

/* @var $this yii\web\View */
/* @var $model common\models\InfoContentArticle */

$this->title = 'Nuevo artículo informativo';
$this->params['breadcrumbs'][] = ['label' => 'Contenido informativo', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="info-content-article-create">

    <h1><?= Html::encode($this->title) ?></h1>

    <?= $this->render('_form', ['model' => $model]) ?>

</div>
