<?php

use yii\helpers\Html;
use yii\widgets\DetailView;
use common\models\Clinical\EncounterDefinition;

/* @var $this yii\web\View */
/* @var $model common\models\Clinical\EncounterDefinition */

$this->title = (string) $model->id;
$this->params['breadcrumbs'][] = ['label' => 'Definiciones de encounter', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="card">
    <div class="card-header d-flex justify-content-between">
        <h4 class="card-title"><?= Html::encode($this->title) ?></h4>
        <?= Html::a('Editar', ['update', 'id' => $model->id], ['class' => 'btn btn-primary']) ?>
    </div>
    <div class="card-body">
        <?= DetailView::widget([
            'model' => $model,
            'attributes' => [
                [
                    'attribute' => 'service_id',
                    'label' => 'Servicio',
                    'value' => static function ($data) {
                        return $data->servicio->nombre ?? $data->service_id;
                    },
                ],
                [
                    'attribute' => 'encounter_class',
                    'label' => 'Tipo de atención',
                    'value' => static function ($data) {
                        return EncounterDefinition::ENCOUNTER_CLASS[$data->encounter_class] ?? $data->encounter_class;
                    },
                ],
                [
                    'attribute' => 'workflow_json',
                    'format' => 'ntext',
                ],
                'created_at',
            ],
        ]) ?>
    </div>
</div>
