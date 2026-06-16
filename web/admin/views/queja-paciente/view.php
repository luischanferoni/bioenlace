<?php

use yii\helpers\Html;
use yii\widgets\DetailView;
use common\models\QuejaPaciente;

/* @var $this yii\web\View */
/* @var $model common\models\QuejaPaciente */

$this->title = 'Queja #' . $model->id;
$this->params['breadcrumbs'][] = ['label' => 'Quejas de pacientes', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="queja-paciente-view">
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h1 class="card-title mt-1 mb-0"><?= Html::encode($this->title) ?></h1>
            <?= Html::a('Volver al listado', ['index'], ['class' => 'btn btn-outline-secondary btn-sm']) ?>
        </div>
        <div class="card-body">
            <?= DetailView::widget([
                'model' => $model,
                'attributes' => [
                    'id',
                    [
                        'attribute' => 'created_at',
                        'label' => 'Fecha',
                        'format' => ['datetime', 'php:d/m/Y H:i'],
                    ],
                    [
                        'attribute' => 'categoria',
                        'label' => 'Categoría',
                        'value' => QuejaPaciente::categoriaLabel((string) $model->categoria),
                    ],
                    [
                        'attribute' => 'id_persona',
                        'label' => 'Paciente',
                        'value' => static function (QuejaPaciente $model) {
                            $p = $model->persona;
                            if ($p === null) {
                                return '#' . $model->id_persona;
                            }

                            return trim($p->apellido . ', ' . $p->nombre) . ' (ID ' . $model->id_persona . ')';
                        },
                    ],
                    'descripcion:ntext',
                ],
            ]) ?>
        </div>
    </div>
</div>
