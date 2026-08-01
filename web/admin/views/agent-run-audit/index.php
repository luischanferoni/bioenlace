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

$this->title = 'Auditoría agent_run';
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="agent-run-audit-index">
    <div class="card">
        <div class="card-header">
            <h1 class="card-title mt-1 mb-0"><?= Html::encode($this->title) ?></h1>
        </div>
        <div class="card-body">
            <p class="text-muted small">
                Pasos decisorios de todos los agentes autónomos. Solo superadmin.
                Módulos ricos:
                <?= Html::a('A03 adelantamiento', ['/adelantamiento-audit/index']) ?>,
                <?= Html::a('A04 anti no-show', ['/antinoshow-audit/index']) ?>,
                <?= Html::a('A01/A02/A06 resolución', ['/resolucion-audit/index']) ?>,
                <?= Html::a('captura clínica', ['/captura-clinica-audit/index']) ?>.
            </p>

            <form method="get" action="<?= Html::encode(Url::to(['index'])) ?>" class="mb-3">
                <div class="row g-2 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label small mb-0">Agente</label>
                        <?= Html::dropDownList(
                            'agent_id',
                            $filters['agent_id'] ?? '',
                            $agentOptions,
                            ['class' => 'form-select form-select-sm', 'prompt' => 'Todos']
                        ) ?>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small mb-0">Outcome</label>
                        <?= Html::textInput('outcome', $filters['outcome'] ?? '', [
                            'class' => 'form-control form-control-sm',
                        ]) ?>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small mb-0">Trigger type</label>
                        <?= Html::textInput('trigger_type', $filters['trigger_type'] ?? '', [
                            'class' => 'form-control form-control-sm',
                        ]) ?>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small mb-0">Trigger ID</label>
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
                    <div class="col-md-1">
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
                    'trigger_type',
                    'trigger_id',
                    'subject_persona_id',
                    'rule_id',
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
