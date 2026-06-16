<?php

use yii\helpers\Html;
use yii\grid\GridView;
use common\models\QuejaPaciente;

/* @var $this yii\web\View */
/* @var $dataProvider yii\data\ActiveDataProvider */

$this->title = 'Quejas de pacientes';
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="queja-paciente-index">
    <div class="card">
        <div class="card-header">
            <h1 class="card-title mt-1"><?= Html::encode($this->title) ?></h1>
        </div>
        <div class="card-body">
            <p class="text-muted small">
                Quejas operativas enviadas desde la app del paciente. Solo visible para superadmin.
            </p>
            <?= GridView::widget([
                'dataProvider' => $dataProvider,
                'columns' => [
                    'id',
                    [
                        'attribute' => 'created_at',
                        'label' => 'Fecha',
                        'format' => ['datetime', 'php:d/m/Y H:i'],
                    ],
                    [
                        'attribute' => 'categoria',
                        'label' => 'Categoría',
                        'value' => static function (QuejaPaciente $model) {
                            return QuejaPaciente::categoriaLabel((string) $model->categoria);
                        },
                    ],
                    [
                        'attribute' => 'id_persona',
                        'label' => 'Paciente',
                        'value' => static function (QuejaPaciente $model) {
                            $p = $model->persona;
                            if ($p === null) {
                                return '#' . $model->id_persona;
                            }

                            return trim($p->apellido . ', ' . $p->nombre);
                        },
                    ],
                    [
                        'attribute' => 'descripcion',
                        'format' => 'ntext',
                        'value' => static function (QuejaPaciente $model) {
                            return \yii\helpers\StringHelper::truncate((string) $model->descripcion, 80);
                        },
                    ],
                    [
                        'class' => 'yii\grid\ActionColumn',
                        'template' => '{view}',
                    ],
                ],
            ]); ?>
        </div>
    </div>
</div>
