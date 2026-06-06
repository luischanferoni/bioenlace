<?php

use yii\helpers\Html;
use yii\grid\GridView;

/* @var $this yii\web\View */
/* @var $dataProvider yii\data\ActiveDataProvider */

$this->title = 'Permisos por atributo (grants BD)';
$this->params['breadcrumbs'][] = ['label' => 'Consultas staff', 'url' => ['#']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="data-access-grant-index">

    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h2 class="card-title mt-1 mb-0"><?= Html::encode($this->title) ?></h2>
                <div>
                    <?= Html::a('Catálogo YAML', ['data-access-catalog/index'], ['class' => 'btn btn-outline-secondary btn-sm']) ?>
                    <?= Html::a('Nuevo grant', ['create'], ['class' => 'btn btn-success btn-sm']) ?>
                </div>
            </div>
            <div class="card-body">
                <p class="text-muted small">
                    Overrides en base de datos sobre <code>attribute_groups_v1.yaml</code>.
                    Si existe fila activa para rol + grupo, prevalece sobre el YAML.
                </p>
                <?= GridView::widget([
                    'dataProvider' => $dataProvider,
                    'columns' => [
                        'id',
                        'role_name',
                        'entity_group_key',
                        'operations_csv',
                        'scope_checker',
                        [
                            'attribute' => 'active',
                            'format' => 'raw',
                            'value' => static function ($model) {
                                return (int) $model->active === 1
                                    ? '<span class="badge bg-success">Sí</span>'
                                    : '<span class="badge bg-secondary">No</span>';
                            },
                        ],
                        [
                            'attribute' => 'notas',
                            'contentOptions' => ['style' => 'max-width: 200px; white-space: normal;'],
                        ],
                        ['class' => 'yii\grid\ActionColumn'],
                    ],
                ]); ?>
            </div>
        </div>
    </div>
</div>
