<?php

use common\components\Platform\Agent\AgentRunAuditQueryService;
use common\models\Platform\AgentRun;
use yii\grid\GridView;
use yii\helpers\Html;
use yii\helpers\Url;

/* @var $this yii\web\View */
/* @var $dataProvider yii\data\ActiveDataProvider */
/* @var $filters array<string, mixed> */
/* @var $agentOptions array<string, string> */
/* @var $title string */
/* @var $failedOnly bool */

$this->title = $title;
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="resolucion-audit-index">
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h1 class="card-title mt-1 mb-0"><?= Html::encode($this->title) ?></h1>
            <div>
                <?php if ($failedOnly): ?>
                    <?= Html::a('Ver todas', ['index'], ['class' => 'btn btn-outline-secondary btn-sm']) ?>
                <?php else: ?>
                    <?= Html::a('Alto impacto', ['fallos'], ['class' => 'btn btn-outline-danger btn-sm']) ?>
                <?php endif; ?>
            </div>
        </div>
        <div class="card-body">
            <p class="text-muted small">
                Shortlist / auto-reserva (A01), multicanal (A02) y cierre de loop (A06). Solo superadmin.
            </p>

            <form method="get" action="<?= Html::encode(Url::to($failedOnly ? ['fallos'] : ['index'])) ?>" class="mb-3">
                <div class="row g-2 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label small mb-0">Agente</label>
                        <?= Html::dropDownList(
                            'agent_id',
                            $filters['agent_id'] ?? '',
                            $agentOptions,
                            ['class' => 'form-select form-select-sm', 'prompt' => 'Todos (familia)']
                        ) ?>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small mb-0">Outcome</label>
                        <?= Html::textInput('outcome', $filters['outcome'] ?? '', [
                            'class' => 'form-control form-control-sm',
                        ]) ?>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small mb-0">Turno (trigger)</label>
                        <?= Html::textInput('trigger_id', $filters['trigger_id'] ?: '', [
                            'class' => 'form-control form-control-sm',
                            'type' => 'number',
                        ]) ?>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small mb-0">Persona</label>
                        <?= Html::textInput('subject_persona_id', $filters['subject_persona_id'] ?: '', [
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
                        'attribute' => 'created_at',
                        'format' => ['datetime', 'php:d/m/Y H:i'],
                    ],
                    [
                        'attribute' => 'agent_id',
                        'value' => static function (AgentRun $m) {
                            return AgentRunAuditQueryService::agentLabel((string) $m->agent_id);
                        },
                    ],
                    'outcome',
                    [
                        'attribute' => 'trigger_id',
                        'label' => 'Turno',
                    ],
                    'subject_persona_id',
                    [
                        'class' => 'yii\grid\ActionColumn',
                        'template' => '{view}',
                        'urlCreator' => static function ($action, AgentRun $model) {
                            return Url::to([$action, 'id' => $model->id]);
                        },
                    ],
                ],
            ]); ?>
        </div>
    </div>
</div>
