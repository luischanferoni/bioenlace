<?php

use common\components\Platform\Agent\AgentRunAuditQueryService;
use common\models\Platform\AgentRun;
use yii\helpers\Html;
use yii\helpers\Json;
use yii\helpers\Url;
use yii\widgets\DetailView;

/* @var $this yii\web\View */
/* @var $model AgentRun */
/* @var $siblings list<AgentRun> */

$this->title = 'agent_run #' . $model->id;
$this->params['breadcrumbs'][] = ['label' => 'Auditoría agent_run', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;

$facts = AgentRunAuditQueryService::decodeJson($model->facts_json ?? null);
$decision = AgentRunAuditQueryService::decodeJson($model->decision_json ?? null);
?>
<div class="agent-run-audit-view">
    <div class="card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h1 class="card-title mt-1 mb-0"><?= Html::encode($this->title) ?></h1>
            <?= Html::a('Listado', ['index'], ['class' => 'btn btn-outline-secondary btn-sm']) ?>
        </div>
        <div class="card-body">
            <?= DetailView::widget([
                'model' => $model,
                'attributes' => [
                    'id',
                    'created_at:datetime',
                    [
                        'attribute' => 'agent_id',
                        'value' => AgentRunAuditQueryService::agentLabel((string) $model->agent_id)
                            . ' (' . $model->agent_id . ')',
                    ],
                    'outcome',
                    'trigger_type',
                    'trigger_id',
                    'subject_persona_id',
                    'encounter_id',
                    'rule_id',
                    'policy_id',
                    'policy_version',
                    'execution_mode',
                ],
            ]) ?>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header"><h2 class="h5 mb-0">facts_json</h2></div>
        <div class="card-body">
            <pre class="small mb-0" style="max-height: 320px; overflow: auto;"><?= Html::encode(
                Json::encode($facts, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
            ) ?></pre>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header"><h2 class="h5 mb-0">decision_json</h2></div>
        <div class="card-body">
            <pre class="small mb-0" style="max-height: 320px; overflow: auto;"><?= Html::encode(
                Json::encode($decision, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
            ) ?></pre>
        </div>
    </div>

    <?php if ($siblings !== []): ?>
        <div class="card mb-3">
            <div class="card-header"><h2 class="h5 mb-0">Runs del mismo trigger</h2></div>
            <div class="card-body">
                <ul class="mb-0">
                    <?php foreach ($siblings as $sib): ?>
                        <li>
                            <?= Html::a(
                                '#' . $sib->id . ' ' . $sib->outcome . ' @ ' . $sib->created_at,
                                ['view', 'id' => $sib->id]
                            ) ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    <?php endif; ?>
</div>
