<?php

use yii\helpers\Html;
use yii\grid\GridView;
use common\models\InfoContentArticle;

/* @var $this yii\web\View */
/* @var $searchModel common\models\busquedas\InfoContentArticleBusqueda */
/* @var $dataProvider yii\data\ActiveDataProvider */

$this->title = 'Contenido informativo';
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="info-content-article-index">
    <div class="card">
        <div class="card-header">
            <div class="header-title d-flex align-items-self justify-content-between">
                <h1><?= Html::encode($this->title) ?></h1>
                <p>
                    <?= Html::a('Nuevo artículo', ['create'], ['class' => 'btn btn-success']) ?>
                </p>
            </div>
        </div>
        <div class="card-body">
            <?= GridView::widget([
                'dataProvider' => $dataProvider,
                'filterModel' => $searchModel,
                'columns' => [
                    ['class' => 'yii\grid\SerialColumn'],
                    'topic',
                    'title',
                    [
                        'attribute' => 'scope',
                        'filter' => InfoContentArticle::scopeLabels(),
                        'value' => function (InfoContentArticle $m) {
                            return InfoContentArticle::scopeLabels()[$m->scope] ?? $m->scope;
                        },
                    ],
                    [
                        'attribute' => 'id_provincia',
                        'label' => 'Provincia',
                        'value' => function (InfoContentArticle $m) {
                            return $m->provincia ? $m->provincia->nombre : '-';
                        },
                    ],
                    [
                        'attribute' => 'id_efector',
                        'label' => 'Centro',
                        'value' => function (InfoContentArticle $m) {
                            return $m->efector ? $m->efector->nombre : '-';
                        },
                    ],
                    [
                        'attribute' => 'activo',
                        'filter' => [0 => 'Inactivo', 1 => 'Activo'],
                        'value' => function (InfoContentArticle $m) {
                            return $m->activo ? 'Activo' : 'Inactivo';
                        },
                    ],
                    'priority',
                    ['class' => 'yii\grid\ActionColumn'],
                ],
            ]); ?>
        </div>
    </div>
</div>
