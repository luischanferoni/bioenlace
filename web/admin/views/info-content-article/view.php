<?php

use yii\helpers\Html;
use yii\widgets\DetailView;
use common\models\InfoContentArticle;

/* @var $this yii\web\View */
/* @var $model common\models\InfoContentArticle */

$this->title = $model->title;
$this->params['breadcrumbs'][] = ['label' => 'Contenido informativo', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
\yii\web\YiiAsset::register($this);
?>
<div class="info-content-article-view">

    <h1><?= Html::encode($this->title) ?></h1>

    <p>
        <?= Html::a('Editar', ['update', 'id' => $model->id], ['class' => 'btn btn-primary']) ?>
        <?= Html::a('Eliminar', ['delete', 'id' => $model->id], [
            'class' => 'btn btn-danger',
            'data' => [
                'confirm' => '¿Seguro que querés eliminar este artículo?',
                'method' => 'post',
            ],
        ]) ?>
    </p>

    <?= DetailView::widget([
        'model' => $model,
        'attributes' => [
            'id',
            'topic',
            'title',
            'body:ntext',
            [
                'attribute' => 'scope',
                'value' => InfoContentArticle::scopeLabels()[$model->scope] ?? $model->scope,
            ],
            [
                'attribute' => 'id_provincia',
                'value' => $model->provincia ? $model->provincia->nombre : '-',
            ],
            [
                'attribute' => 'id_efector',
                'value' => $model->efector ? $model->efector->nombre : '-',
            ],
            'keywords',
            [
                'attribute' => 'activo',
                'value' => $model->activo ? 'Activo' : 'Inactivo',
            ],
            'priority',
            'created_at',
            'updated_at',
        ],
    ]) ?>

</div>
