<?php

use yii\helpers\Html;
use yii\widgets\DetailView;

/* @var $this yii\web\View */
/* @var $model common\models\DataAccess\DataAccessAttributeField */

$this->title = $model->field_name . ' @ ' . $model->entity_group_key;
$this->params['breadcrumbs'][] = ['label' => 'Campos por grupo', 'url' => ['index', 'group' => $model->entity_group_key]];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="data-access-attribute-field-view">

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h2 mb-0"><?= Html::encode($this->title) ?></h1>
        <div>
            <?= Html::a('Editar', ['update', 'id' => $model->id], ['class' => 'btn btn-primary btn-sm']) ?>
            <?= Html::a('Eliminar', ['delete', 'id' => $model->id], [
                'class' => 'btn btn-outline-danger btn-sm',
                'data' => [
                    'confirm' => '¿Eliminar este campo?',
                    'method' => 'post',
                ],
            ]) ?>
        </div>
    </div>

    <?= DetailView::widget([
        'model' => $model,
        'attributes' => [
            'id',
            'entity_group_key',
            'field_name',
            'field_type',
            'label',
            [
                'attribute' => 'config_json',
                'format' => 'ntext',
                'value' => $model->configJsonForForm(),
            ],
            'sort_order',
            'active:boolean',
        ],
    ]) ?>

</div>
