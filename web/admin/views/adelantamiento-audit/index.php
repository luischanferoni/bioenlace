<?php

use common\models\Scheduling\TurnoAdvanceCampaign;
use yii\grid\GridView;
use yii\helpers\Html;
use yii\helpers\Url;

/* @var $this yii\web\View */
/* @var $dataProvider yii\data\ActiveDataProvider */
/* @var $filters array<string, mixed> */
/* @var $estados list<string> */
/* @var $title string */
/* @var $failedOnly bool */

$this->title = $title;
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="adelantamiento-audit-index">
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h1 class="card-title mt-1 mb-0"><?= Html::encode($this->title) ?></h1>
            <div>
                <?php if ($failedOnly): ?>
                    <?= Html::a('Ver todas', ['index'], ['class' => 'btn btn-outline-secondary btn-sm']) ?>
                <?php else: ?>
                    <?= Html::a('Fallos / atascadas', ['fallos'], ['class' => 'btn btn-outline-danger btn-sm']) ?>
                <?php endif; ?>
            </div>
        </div>
        <div class="card-body">
            <p class="text-muted small">
                Campañas del agente A03 (adelantamiento tras cancelación: ofertas D+2 / D+1). Solo superadmin.
            </p>

            <form method="get" action="<?= Html::encode(Url::to($failedOnly ? ['fallos'] : ['index'])) ?>" class="mb-3">
                <div class="row g-2 align-items-end">
                    <?php if (!$failedOnly): ?>
                        <div class="col-md-2">
                            <label class="form-label small mb-0">Estado</label>
                            <?= Html::dropDownList(
                                'estado',
                                $filters['estado'] ?? '',
                                array_combine($estados, $estados),
                                ['class' => 'form-select form-select-sm', 'prompt' => 'Todos']
                            ) ?>
                        </div>
                    <?php endif; ?>
                    <div class="col-md-2">
                        <label class="form-label small mb-0">Efector</label>
                        <?= Html::textInput('id_efector', $filters['id_efector'] ?: '', [
                            'class' => 'form-control form-control-sm',
                            'type' => 'number',
                        ]) ?>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small mb-0">Servicio</label>
                        <?= Html::textInput('id_servicio', $filters['id_servicio'] ?: '', [
                            'class' => 'form-control form-control-sm',
                            'type' => 'number',
                        ]) ?>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small mb-0">Turno cancelado</label>
                        <?= Html::textInput('id_cancelled_turno', $filters['id_cancelled_turno'] ?: '', [
                            'class' => 'form-control form-control-sm',
                            'type' => 'number',
                        ]) ?>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small mb-0">Fecha slot</label>
                        <?= Html::textInput('slot_fecha', $filters['slot_fecha'] ?? '', [
                            'class' => 'form-control form-control-sm',
                            'type' => 'date',
                        ]) ?>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary btn-sm">Filtrar</button>
                    </div>
                </div>
            </form>

            <?= GridView::widget([
                'dataProvider' => $dataProvider,
                'columns' => [
                    'id',
                    [
                        'attribute' => 'created_at',
                        'label' => 'Creada',
                        'format' => ['datetime', 'php:d/m/Y H:i'],
                    ],
                    'estado',
                    [
                        'attribute' => 'stop_reason',
                        'label' => 'Stop',
                        'value' => static function (TurnoAdvanceCampaign $m) {
                            return $m->stop_reason ?: '—';
                        },
                    ],
                    [
                        'label' => 'Slot',
                        'value' => static function (TurnoAdvanceCampaign $m) {
                            return $m->fecha . ' ' . $m->hora;
                        },
                    ],
                    'id_efector',
                    'id_servicio',
                    [
                        'attribute' => 'id_cancelled_turno',
                        'label' => 'Cancelado',
                    ],
                    [
                        'attribute' => 'id_turno_filled',
                        'label' => 'Llenó',
                        'value' => static function (TurnoAdvanceCampaign $m) {
                            return $m->id_turno_filled ?: '—';
                        },
                    ],
                    'current_sequence',
                    [
                        'attribute' => 'next_run_at',
                        'label' => 'Next run',
                        'value' => static function (TurnoAdvanceCampaign $m) {
                            return $m->next_run_at ?: '—';
                        },
                    ],
                    [
                        'class' => 'yii\grid\ActionColumn',
                        'template' => '{view}',
                        'urlCreator' => static function ($action, TurnoAdvanceCampaign $model) {
                            return Url::to([$action, 'id' => $model->id]);
                        },
                    ],
                ],
            ]); ?>
        </div>
    </div>
</div>
