<?php

use common\models\TurnoNotificacionProgramada;
use yii\grid\GridView;
use yii\helpers\Html;
use yii\helpers\Url;

/* @var $this yii\web\View */
/* @var $dataProvider yii\data\ActiveDataProvider */
/* @var $filters array<string, mixed> */
/* @var $tipos list<string> */
/* @var $title string */
/* @var $failedOnly bool */

$this->title = $title;
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="antinoshow-audit-index">
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h1 class="card-title mt-1 mb-0"><?= Html::encode($this->title) ?></h1>
            <div>
                <?php if ($failedOnly): ?>
                    <?= Html::a('Ver todas', ['index'], ['class' => 'btn btn-outline-secondary btn-sm']) ?>
                <?php else: ?>
                    <?= Html::a('Fallos', ['fallos'], ['class' => 'btn btn-outline-danger btn-sm']) ?>
                <?php endif; ?>
                <?= Html::a('agent_run', ['/agent-run-audit/index', 'agent_id' => 'turno-antinoshow'], [
                    'class' => 'btn btn-outline-secondary btn-sm',
                ]) ?>
            </div>
        </div>
        <div class="card-body">
            <p class="text-muted small">
                Checkpoints T−48h / T−2h y liberaciones T−24h del agente A04. Solo superadmin.
            </p>

            <form method="get" action="<?= Html::encode(Url::to($failedOnly ? ['fallos'] : ['index'])) ?>" class="mb-3">
                <div class="row g-2 align-items-end">
                    <div class="col-md-2">
                        <label class="form-label small mb-0">Tipo</label>
                        <?= Html::dropDownList(
                            'tipo',
                            $filters['tipo'] ?? '',
                            array_combine($tipos, $tipos),
                            ['class' => 'form-select form-select-sm', 'prompt' => 'Todos']
                        ) ?>
                    </div>
                    <?php if (!$failedOnly): ?>
                        <div class="col-md-2">
                            <label class="form-label small mb-0">Estado</label>
                            <?= Html::dropDownList(
                                'estado',
                                $filters['estado'] ?? '',
                                [
                                    TurnoNotificacionProgramada::ESTADO_PENDIENTE => 'PENDIENTE',
                                    TurnoNotificacionProgramada::ESTADO_ENVIADA => 'ENVIADA',
                                    TurnoNotificacionProgramada::ESTADO_CANCELADA => 'CANCELADA',
                                    TurnoNotificacionProgramada::ESTADO_FALLIDA => 'FALLIDA',
                                ],
                                ['class' => 'form-select form-select-sm', 'prompt' => 'Todos']
                            ) ?>
                        </div>
                    <?php endif; ?>
                    <div class="col-md-2">
                        <label class="form-label small mb-0">Turno</label>
                        <?= Html::textInput('id_turno', $filters['id_turno'] ?: '', [
                            'class' => 'form-control form-control-sm',
                            'type' => 'number',
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
                        'attribute' => 'run_at',
                        'format' => ['datetime', 'php:d/m/Y H:i'],
                    ],
                    'tipo',
                    'estado',
                    'id_turno',
                    'intentos',
                    [
                        'attribute' => 'ultimo_error',
                        'value' => static function (TurnoNotificacionProgramada $m) {
                            $err = trim((string) $m->ultimo_error);
                            return $err === '' ? '' : \yii\helpers\StringHelper::truncate($err, 50);
                        },
                    ],
                    [
                        'class' => 'yii\grid\ActionColumn',
                        'template' => '{view}',
                        'urlCreator' => static function ($action, TurnoNotificacionProgramada $model) {
                            return Url::to([$action, 'id' => $model->id]);
                        },
                    ],
                ],
            ]); ?>
        </div>
    </div>
</div>
